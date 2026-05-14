<?php

namespace App\Repository;

use App\Entity\WatchHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WatchHistory>
 */
class WatchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WatchHistory::class);
    }

    public function findByWatcherAndMedia(int $watcherId, int $mediaId): ?WatchHistory
    {
        return $this->createQueryBuilder('w')
            ->where('w.watcher = :watcher')
            ->andWhere('w.media = :media')
            ->setParameter('watcher', $watcherId)
            ->setParameter('media', $mediaId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return WatchHistory[] */
    public function findByWatcherOrderedByDate(int $watcherId): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.watcher = :watcher')
            ->setParameter('watcher', $watcherId)
            ->orderBy('w.lastWatchedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
