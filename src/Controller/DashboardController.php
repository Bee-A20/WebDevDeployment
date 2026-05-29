<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ProductsRepository $productsRepository,
        CustomerRepository $customerRepository,
        OrdersRepository $ordersRepository
    ): Response {
        $stats = $this->buildDashboardStats($productsRepository, $customerRepository, $ordersRepository);

        $response = $this->render('dashboard/index.html.twig', $stats);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/dashboard/poll', name: 'app_dashboard_poll', methods: ['GET'])]
    public function poll(
        ProductsRepository $productsRepository,
        CustomerRepository $customerRepository,
        OrdersRepository $ordersRepository
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException();
        }

        $stats = $this->buildDashboardStats($productsRepository, $customerRepository, $ordersRepository);

        $response = new JsonResponse($stats);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * @return array{
     *     totalProducts: int,
     *     totalCustomers: int,
     *     totalOrders: int,
     *     totalStock: int,
     *     lowStockCount: int,
     *     revenue: float
     * }
     */
    private function buildDashboardStats(
        ProductsRepository $productsRepository,
        CustomerRepository $customerRepository,
        OrdersRepository $ordersRepository
    ): array {
        $totalProducts = $productsRepository->countAllProducts();
        $totalCustomers = count($customerRepository->findAll());
        $totalOrders = count($ordersRepository->findAll());
        $totalStock = $productsRepository->sumStock();
        $lowStockCount = $productsRepository->countLowStock(5);

        $orders = $ordersRepository->findAll();
        $revenue = 0.0;
        foreach ($orders as $order) {
            $status = $order->getStatus();
            if (null === $status || strtolower($status) !== 'delivered') {
                continue;
            }

            $revenue += $order->getTotal();
        }

        return [
            'totalProducts' => $totalProducts,
            'totalCustomers' => $totalCustomers,
            'totalOrders' => $totalOrders,
            'totalStock' => $totalStock,
            'lowStockCount' => $lowStockCount,
            'revenue' => round($revenue, 2),
        ];
    }
}
