<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function save(Stock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Stock $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Get all stock entries for a product, ordered by date
     */
    public function findByProductOrderedByDate($product): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.product = :product')
            ->setParameter('product', $product)
            ->orderBy('s.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total stock added for a product
     */
    public function getTotalStockForProduct($product): ?int
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.quantity)')
            ->andWhere('s.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }
}
