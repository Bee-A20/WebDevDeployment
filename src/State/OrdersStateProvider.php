<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Orders;
use App\Repository\OrdersRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class OrdersStateProvider implements ProviderInterface
{
    public function __construct(
        private OrdersRepository $ordersRepository,
        private Security $security,
    ) {}

    public function provide(
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): object|array|null {
        $user = $this->getUser();

        // If there's no ID in uriVariables, it's a collection operation
        if (!isset($uriVariables['id'])) {
            if (!$user) {
                return [];
            }

            // Filter orders by the authenticated user
            $orders = $this->ordersRepository->findBy(['createdBy' => $user]);
            
            return $orders;
        }

        // For single item operations, return null to let API Platform handle it with default provider
        return null;
    }

    private function getUser()
    {
        return $this->security->getUser();
    }
}
