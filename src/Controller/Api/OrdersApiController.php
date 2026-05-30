<?php

namespace App\Controller\Api;

use App\Entity\Orders;
use App\Entity\OrderItem;
use App\Entity\Customer;
use App\Entity\Products;
use App\Repository\OrdersRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name: 'api_')]
final class OrdersApiController extends AbstractController
{
    #[Route('/orders', name: 'orders_list', methods: ['GET'], priority: 10)]
    public function listOrders(
        OrdersRepository $ordersRepository,
        SerializerInterface $serializer
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([], Response::HTTP_UNAUTHORIZED);
        }

        // Get orders for the authenticated user, or all orders for admin/staff
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            $orders = $ordersRepository->findAll();
        } else {
            $orders = $ordersRepository->findBy(['createdBy' => $user]);
        }

        $data = $serializer->serialize($orders, 'json', ['groups' => 'order:read']);

        $response = new Response($data, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/orders', name: 'orders_create', methods: ['POST'], priority: 10)]
    public function createOrder(
        Request $request,
        EntityManagerInterface $entityManager,
        CustomerRepository $customersRepository,
        ProductsRepository $productsRepository,
        SerializerInterface $serializer
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = json_decode($request->getContent(), true);

            // Extract customer reference
            $customerRef = $data['customer'] ?? $data['customer_id'] ?? null;
            if (!$customerRef) {
                return new JsonResponse(['message' => 'Customer reference is required'], Response::HTTP_BAD_REQUEST);
            }

            // Parse customer ID from IRI or ID
            $customerId = $customerRef;
            if (is_string($customerRef) && strpos($customerRef, '/') !== false) {
                // Extract ID from IRI like "/api/customers/1"
                $parts = explode('/', trim($customerRef, '/'));
                $customerId = end($parts);
            }

            $customer = $customersRepository->find((int)$customerId);
            if (!$customer) {
                return new JsonResponse(['message' => 'Customer not found'], Response::HTTP_NOT_FOUND);
            }

            // Create order
            $order = new Orders();
            $order->setCreatedBy($user);
            $order->setCustomer($customer);
            $order->setStatus($data['status'] ?? 'pending');

            // Process order items
            $items = $data['orderItems'] ?? $data['items'] ?? $data['products'] ?? [];
            foreach ($items as $itemData) {
                // Extract product reference
                $productRef = $itemData['product'] ?? $itemData['product_id'] ?? null;
                if (!$productRef) {
                    continue;
                }

                // Parse product ID from IRI or ID
                $productId = $productRef;
                if (is_string($productRef) && strpos($productRef, '/') !== false) {
                    // Extract ID from IRI like "/api/products/1"
                    $parts = explode('/', trim($productRef, '/'));
                    $productId = end($parts);
                }

                $product = $productsRepository->find((int)$productId);
                if (!$product) {
                    continue;
                }

                $orderItem = new OrderItem();
                $orderItem->setProduct($product);
                $orderItem->setQuantity((int)($itemData['quantity'] ?? 1));
                $orderItem->setOrder($order);

                $order->addOrderItem($orderItem);
            }

            if (strtolower((string) $order->getStatus()) === 'delivered') {
                $this->reduceStockForDeliveredOrder($order);
            }

            $entityManager->persist($order);
            $entityManager->flush();

            $jsonData = $serializer->serialize($order, 'json', ['groups' => 'order:read']);

            return new Response($jsonData, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['message' => 'Error creating order: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/orders/{id}/cancel', name: 'orders_cancel', methods: ['PATCH'], requirements: ['id' => '\d+'], priority: 20)]
    public function cancelOrder(
        int $id,
        OrdersRepository $ordersRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $ordersRepository->find($id);
        if (!$order) {
            return new JsonResponse(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_STAFF')
            && $order->getCreatedBy()?->getId() !== $user->getId()
        ) {
            return new JsonResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $status = strtolower((string) $order->getStatus());
        if (in_array($status, ['delivered', 'cancelled'], true)) {
            return new JsonResponse(
                ['message' => 'Delivered or cancelled orders cannot be cancelled'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $order->setStatus('cancelled');
        $entityManager->flush();

        $jsonData = $serializer->serialize($order, 'json', ['groups' => 'order:read']);

        return new Response($jsonData, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    private function reduceStockForDeliveredOrder(Orders $order): void
    {
        foreach ($order->getOrderItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $quantity = $orderItem->getQuantity() ?? 0;
            if (!$product || $quantity <= 0) {
                continue;
            }

            $stock = $product->getStock();
            if ($stock === null) {
                continue;
            }

            $product->setStock(max(0, $stock - $quantity));
        }
    }
}
