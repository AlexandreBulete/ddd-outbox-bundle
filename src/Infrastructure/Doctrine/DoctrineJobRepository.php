<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine;

use AlexandreBulete\DddDoctrineBridge\DoctrineRepository;
use AlexandreBulete\DddFoundation\Domain\ValueObject\IdentifierVO;
use AlexandreBulete\DddOutboxBundle\Domain\Model\Job;
use AlexandreBulete\DddOutboxBundle\Domain\Repository\JobRepositoryInterface;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends DoctrineRepository<Job>
 */
final class DoctrineJobRepository extends DoctrineRepository implements JobRepositoryInterface
{
    private const ALIAS = 'job';

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Job::class, self::ALIAS);
    }

    public function findById(IdentifierVO $id): ?Job
    {
        return $this->em->find(Job::class, $id->value());
    }

    public function purgeSucceededBefore(\DateTimeImmutable $before): int
    {
        $removed = $this->em->createQueryBuilder()
            ->delete(Job::class, self::ALIAS)
            ->where(self::ALIAS . '.status = :succeeded')
            ->andWhere(self::ALIAS . '.finishedAt < :before')
            ->setParameter('succeeded', JobStatus::Succeeded)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();

        return is_int($removed) && $removed > 0 ? $removed : 0;
    }
}
