<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionTypeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findByUserWithFilters(User $user, array $filters = [])
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->leftJoin('t.course', 'c')
            ->addSelect('c')
            ->andWhere('t.userBilling = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if (!empty($filters['type'])) {
            $types = $this->mapTypeFilters((array) $filters['type']);

            if ($types !== []) {
                $queryBuilder
                    ->andWhere('t.type IN (:types)')
                    ->setParameter('types', $types);
            }
        }

        if (!empty($filters['course_code'])) {
            $queryBuilder
                ->andWhere('c.code = :courseCode')
                ->setParameter('courseCode', $filters['course_code']);
        }

        if (($filters['skip_expired'] ?? null) === '1' || ($filters['skip_expired'] ?? null) === 'true') {
            $queryBuilder
                ->andWhere('t.expiresAt IS NULL OR t.expiresAt > :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function mapTypeFilters(array $types): array
    {
        $result = [];

        foreach ($types as $type) {
            $enum = TransactionTypeEnum::fromCode($type);

            if ($enum !== null) {
                $result[] = $enum->value;
            }
        }

        return $result;
    }
}
