<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Orders;
use App\Entity\OrderItem;
use App\Repository\CustomerRepository;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

#[Route('/cart')]
final class CartController extends AbstractController
{
    use TargetPathTrait;

    #[Route('', name: 'app_cart', methods: ['GET'])]
    public function index(Request $request, ProductsRepository $productsRepository): Response
    {
        $cart = $request->getSession()->get('shop_cart', []);
        $items = [];
        $subtotal = 0.0;

        foreach ($cart as $productId => $quantity) {
            $product = $productsRepository->find($productId);
            if (!$product) {
                continue;
            }

            $price = (float) $product->getPrice();
            $lineTotal = $price * $quantity;
            $subtotal += $lineTotal;

            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'lineTotal' => $lineTotal,
            ];
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'subtotal' => $subtotal,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function addToCart(int $id, Request $request, ProductsRepository $productsRepository): RedirectResponse
    {
        $product = $productsRepository->find($id);
        if (!$product) {
            $this->addFlash('danger', 'Product not found.');
            return $this->redirectToRoute('app_landing_page');
        }

        if (!$this->isCsrfTokenValid('add-to-cart'.$product->getId(), $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Unable to add product to cart.');
            return $this->redirectToRoute('app_landing_page');
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));

        if (!$this->getUser()) {
            $session = $request->getSession();
            $session->set('shop_cart_pending', [
                'product_id' => $id,
                'quantity' => $quantity,
            ]);
            $this->addFlash('info', 'Please log in first to add this item to your cart.');

            $referer = $request->headers->get('referer', '');
            $redirectPath = $referer ? (string) parse_url($referer, PHP_URL_PATH) : $this->generateUrl('app_landing_page');
            if ($redirectPath === '') {
                $redirectPath = $this->generateUrl('app_landing_page');
            }

            $this->saveTargetPath($session, 'main', $redirectPath);
            return $this->redirectToRoute('app_login', ['redirect' => $redirectPath]);
        }

        $stock = $product->getStock();
        if ($stock !== null && $stock <= 0) {
            $this->addFlash('warning', 'This product is out of stock.');
            return $this->redirectToRoute('app_shop');
        }

        $session = $request->getSession();
        $cart = $session->get('shop_cart', []);
        $cart[$id] = ($cart[$id] ?? 0) + $quantity;

        if ($stock !== null && $cart[$id] > $stock) {
            $cart[$id] = $stock;
            $this->addFlash('info', 'Quantity limited by available stock.');
        }

        $session->set('shop_cart', $cart);
        $this->addFlash('success', sprintf('Added "%s" to your cart.', $product->getName()));

        return $this->redirectToRoute('app_shop');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function removeFromCart(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('remove-from-cart'.$id, $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Unable to update cart.');
            return $this->redirectToRoute('app_cart');
        }

        $session = $request->getSession();
        $cart = $session->get('shop_cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
            $session->set('shop_cart', $cart);
            $this->addFlash('success', 'Item removed from your cart.');
        }

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function updateQuantity(int $id, Request $request, ProductsRepository $productsRepository): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('update-cart-item'.$id, $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Unable to update cart quantity.');
            return $this->redirectToRoute('app_cart');
        }

        $product = $productsRepository->find($id);
        if (!$product) {
            $this->addFlash('danger', 'Product not found.');
            return $this->redirectToRoute('app_cart');
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $stock = $product->getStock();

        if ($stock !== null && $quantity > $stock) {
            $quantity = $stock;
            $this->addFlash('info', 'Quantity limited by available stock.');
        }

        $session = $request->getSession();
        $cart = $session->get('shop_cart', []);
        $cart[$id] = $quantity;
        $session->set('shop_cart', $cart);

        $this->addFlash('success', sprintf('Cart updated: %s x %d.', $product->getName(), $quantity));
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/checkout', name: 'app_cart_checkout', methods: ['GET'])]
    public function checkoutPage(Request $request, ProductsRepository $productsRepository): Response
    {
        $cart = $request->getSession()->get('shop_cart', []);
        $items = [];
        $subtotal = 0.0;

        foreach ($cart as $productId => $quantity) {
            $product = $productsRepository->find($productId);
            if (!$product) {
                continue;
            }

            $lineTotal = $product->getPrice() * $quantity;
            $subtotal += $lineTotal;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'lineTotal' => $lineTotal,
            ];
        }

        return $this->render('cart/checkout.html.twig', [
            'items' => $items,
            'subtotal' => $subtotal,
        ]);
    }

    #[Route('/place-order', name: 'app_cart_place_order', methods: ['POST'])]
    public function placeOrder(Request $request, EntityManagerInterface $entityManager, ProductsRepository $productsRepository, CustomerRepository $customerRepository): RedirectResponse
    {
        if (!$this->getUser()) {
            $this->addFlash('warning', 'Please log in to place your order.');
            $this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('app_cart'));
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('checkout-order', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Unable to place your order. Please try again.');
            return $this->redirectToRoute('app_cart_checkout');
        }

        $session = $request->getSession();
        $cart = $session->get('shop_cart', []);
        if (empty($cart)) {
            $this->addFlash('info', 'Your cart is empty. Add items before placing an order.');
            return $this->redirectToRoute('app_cart');
        }

        $order = new Orders();
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setStatus('pending');
        $order->setCreatedBy($this->getUser());

        $totalQuantity = 0;
        foreach ($cart as $productId => $quantity) {
            $product = $productsRepository->find($productId);
            if (!$product) {
                continue;
            }

            $stock = $product->getStock();
            if ($stock !== null && $quantity > $stock) {
                $this->addFlash('danger', sprintf('Not enough stock for "%s".', $product->getName()));
                return $this->redirectToRoute('app_cart_checkout');
            }

            // Stock is reduced when the order status becomes "delivered" (see OrderDeliveryStockSubscriber).

            // Create OrderItem and add to order
            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $order->addOrderItem($orderItem);

            $totalQuantity += $quantity;
        }

        if ($totalQuantity === 0) {
            $this->addFlash('warning', 'No valid products were found in your cart.');
            return $this->redirectToRoute('app_cart');
        }

        $customer = $customerRepository->findOneBy(['createdBy' => $this->getUser()]);
        if (!$customer) {
            $customer = new Customer();
            $customer->setName($this->getUser()->getUserIdentifier());
            $customer->setEmail($this->getUser()->getEmail() ?? sprintf('user+%s@example.com', $this->getUser()->getId()));
            $customer->setPhoneNumber('0000000000');
            $customer->setCreatedAt(new \DateTimeImmutable());
            $customer->setCreatedBy($this->getUser());
            $entityManager->persist($customer);
        }

        $order->setCustomer($customer);

        $entityManager->persist($order);
        $entityManager->flush();

        $session->remove('shop_cart');
        $this->addFlash('success', 'Your order has been placed. You can track it in Orders.');

        return $this->redirectToRoute('app_orders_index');
    }
}

