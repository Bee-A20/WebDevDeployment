<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ProductsRepository $productsRepository,
        CustomerRepository $customerRepository,
        OrdersRepository $ordersRepository
    ): Response
    {
        // Get counts
        $totalProducts = $productsRepository->countAllProducts();
        $totalCustomers = count($customerRepository->findAll());
        $totalOrders = count($ordersRepository->findAll());
        $totalStock = $productsRepository->sumStock();
        $lowStockCount = $productsRepository->countLowStock(5);

        // Calculate revenue from orders excluding cancelled ones
        $orders = $ordersRepository->findAll();
        $revenue = 0.0;
        foreach ($orders as $order) {
            $status = $order->getStatus();
            if (null === $status || strtolower($status) !== 'delivered') {
                continue; // count only delivered orders as actual revenue
            }

            $revenue += $order->getTotal();
        }
        
        $response = $this->render('dashboard/index.html.twig', [
            'totalProducts' => $totalProducts,
            'totalCustomers' => $totalCustomers,
            'totalOrders' => $totalOrders,
            'totalStock' => $totalStock,
            'lowStockCount' => $lowStockCount,
            'revenue' => $revenue,
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
