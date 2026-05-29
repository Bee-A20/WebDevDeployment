<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Entity\OrderItem;
use App\Entity\Products;
use App\Form\OrdersType;
use App\Repository\OrdersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/orders')]
final class OrdersController extends AbstractController
{
    #[Route(name: 'app_orders_index', methods: ['GET'])]
    public function index(OrdersRepository $ordersRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            $orders = $ordersRepository->findAll();
        } else {
            $orders = $ordersRepository->findBy(['createdBy' => $this->getUser()]);
        }

        $response = $this->render('orders/index.html.twig', [
            'orders' => $orders,
        ]);

        // Prevent caching to avoid back button access after logout
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Session-authenticated JSON endpoint for live order polling in the web UI.
     * (The /api/orders route requires JWT and does not receive browser session cookies.)
     */
    #[Route('/poll', name: 'app_orders_poll', methods: ['GET'], priority: 20)]
    public function poll(OrdersRepository $ordersRepository, SerializerInterface $serializer): Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            $orders = $ordersRepository->findBy([], ['createdAt' => 'DESC']);
        } else {
            $orders = $ordersRepository->findBy(
                ['createdBy' => $this->getUser()],
                ['createdAt' => 'DESC']
            );
        }

        $data = $serializer->serialize($orders, 'json', ['groups' => 'order:read']);

        $response = new Response($data, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/new', name: 'app_orders_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_shop');
        }

        $order = new Orders();
        $form = $this->createForm(OrdersType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $adjustStock = 'delivered' === strtolower((string) $order->getStatus());
            if ($this->syncOrderLineItems($order, $form, $entityManager, adjustStock: $adjustStock)) {
                return $this->render('orders/new.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            $order->setCreatedBy($this->getUser());

            $entityManager->persist($order);
            $entityManager->flush();

            $this->addFlash('success', 'Order created successfully.');

            return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('orders/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Orders $order): Response
    {
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF') && $order->getCreatedBy()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have permission to view this order.');
        }

        return $this->render('orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_orders_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Orders $order, EntityManagerInterface $entityManager): Response
    {
        // Prevent editing if order is already delivered or cancelled
        if (null !== $order->getStatus() && in_array(strtolower($order->getStatus()), ['delivered', 'cancelled'], true)) {
            $this->addFlash('warning', 'Delivered or cancelled orders cannot be edited.');
            return $this->redirectToRoute('app_orders_show', ['id' => $order->getId()]);
        }

        $form = $this->createForm(OrdersType::class, $order);
        if (!$request->isMethod('POST')) {
            $this->prefillOrderFormFields($order, $form);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
                throw $this->createAccessDeniedException('You do not have permission to edit this order.');
            }

            if ($this->syncOrderLineItems($order, $form, $entityManager, adjustStock: false)) {
                return $this->render('orders/edit.html.twig', [
                    'order' => $order,
                    'form' => $form,
                ]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Order updated successfully.');

            return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('orders/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Orders $order, EntityManagerInterface $entityManager): Response
    {
        // permission check: admin, staff, or owner can delete
        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF') && $order->getCreatedBy()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You do not have permission to delete this order.');
        }

        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            $entityManager->remove($order);
            $entityManager->flush();
            $this->addFlash('success', 'Order deleted successfully.');
        }

        return $this->redirectToRoute('app_orders_index', [], Response::HTTP_SEE_OTHER);
    }

    private function prefillOrderFormFields(Orders $order, FormInterface $form): void
    {
        if (null === $order->getId() || !$form->has('products') || !$form->has('quantity')) {
            return;
        }

        $products = [];
        $quantities = [];
        foreach ($order->getOrderItems() as $orderItem) {
            $product = $orderItem->getProduct();
            if ($product) {
                $products[$product->getId()] = $product;
            }
            if (null !== $orderItem->getQuantity()) {
                $quantities[] = $orderItem->getQuantity();
            }
        }

        if ($products !== []) {
            $form->get('products')->setData(array_values($products));
        }

        if ($quantities !== []) {
            $uniqueQuantities = array_unique($quantities);
            $form->get('quantity')->setData(
                1 === count($uniqueQuantities) ? reset($uniqueQuantities) : $quantities[0]
            );
        }
    }

    /**
     * Rebuilds order line items from the unmapped products + quantity fields.
     *
     * @return bool true when validation failed (caller should re-render the form)
     */
    private function syncOrderLineItems(
        Orders $order,
        FormInterface $form,
        EntityManagerInterface $entityManager,
        bool $adjustStock,
    ): bool {
        /** @var Products[] $selectedProducts */
        $selectedProducts = $form->get('products')->getData() ?? [];
        $quantity = (int) $form->get('quantity')->getData();

        if ($quantity < 1) {
            $this->addFlash('danger', 'Quantity must be at least 1.');

            return true;
        }

        if ([] === $selectedProducts) {
            $this->addFlash('danger', 'Please select at least one product.');

            return true;
        }

        if ($adjustStock) {
            foreach ($selectedProducts as $product) {
                $stock = $product->getStock();
                if (null !== $stock && $quantity > $stock) {
                    $this->addFlash(
                        'danger',
                        sprintf('Not enough stock for product "%s". Available: %d.', $product->getName(), $stock)
                    );

                    return true;
                }
            }
        }

        foreach ($order->getOrderItems()->toArray() as $existingItem) {
            $order->removeOrderItem($existingItem);
            $entityManager->remove($existingItem);
        }

        foreach ($selectedProducts as $product) {
            if ($adjustStock) {
                $stock = $product->getStock();
                if (null !== $stock) {
                    $product->setStock($stock - $quantity);
                }
            }

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $order->addOrderItem($orderItem);
        }

        return false;
    }

}
