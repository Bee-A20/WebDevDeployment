<?php

namespace App\EventSubscriber;

use App\Entity\Orders;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;

#[AsDoctrineListener(event: Events::onFlush)]
final class OrderDeliveryStockSubscriber
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Orders) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            if (!isset($changeSet['status'])) {
                continue;
            }

            $originalStatus = strtolower((string) ($changeSet['status'][0] ?? ''));
            $newStatus = strtolower((string) ($changeSet['status'][1] ?? ''));

            if ($originalStatus !== 'delivered' && $newStatus === 'delivered') {
                $this->applyDeliveredStockReduction($entity, $em, $uow);
            } elseif ($originalStatus === 'delivered' && $newStatus !== 'delivered') {
                $this->restoreDeliveredStock($entity, $em, $uow);
            }
        }
    }

    private function applyDeliveredStockReduction(Orders $order, EntityManagerInterface $em, UnitOfWork $uow): void
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
            $em->persist($product);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($product::class), $product);
        }
    }

    private function restoreDeliveredStock(Orders $order, EntityManagerInterface $em, UnitOfWork $uow): void
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

            $product->setStock($stock + $quantity);
            $em->persist($product);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($product::class), $product);
        }
    }
}
