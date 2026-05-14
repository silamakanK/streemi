<?php

namespace App\Repository;

use App\Entity\Media;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /** @return Media[] */
    public function findByCategoryWithPagination(Category $category, int $currentPage, int $maxPerPage): array
    {
        // categories ManyToMany relation
        return $this->createQueryBuilder('c')
            ->join('c.categories', 'cat')
            ->where('cat = :category')
            ->setParameter('category', $category)
            ->orderBy('c.id', 'ASC')
            ->setFirstResult(($currentPage - 1) * $maxPerPage)
            ->setMaxResults($maxPerPage)
            ->getQuery()
            ->getResult();

    }

    /** @return Media[] */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('m')
            ->where('LOWER(m.title) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('m.title', 'ASC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();
    }

    public function findPopular()
    {
        // a popular media is a media that has often been added to playlists
        return $this->createQueryBuilder('m')
            ->select('m, COUNT(p) as HIDDEN nbPlaylists')
            ->join('m.playlistMedia', 'p')
            ->groupBy('m.id')
            ->orderBy('nbPlaylists', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

}
