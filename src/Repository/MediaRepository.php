<?php

namespace App\Repository;

use App\Entity\Media;
use App\Entity\Category;
use App\Entity\Movie;
use App\Entity\Serie;
use App\Entity\User;
use App\Enum\CatalogTypeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /** Liste paginée du back-office, avec recherche par titre et filtre par onglet */
    public function findForAdmin(string $query, ?CatalogTypeEnum $type, int $page, int $perPage, ?Category $category = null): Paginator
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($query !== '') {
            $qb->andWhere('LOWER(m.title) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }
        if ($category) {
            $qb->andWhere(':category MEMBER OF m.categories')->setParameter('category', $category);
        }

        return new Paginator($this->filterByCatalogType($qb, $type));
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

    /** @return Media[] */
    public function findPopular(?CatalogTypeEnum $type = null, int $limit = 10): array
    {
        // a popular media is a media that has often been added to playlists
        $qb = $this->createQueryBuilder('m')
            ->select('m, COUNT(p) as HIDDEN nbPlaylists')
            ->leftJoin('m.playlistMedia', 'p')
            ->groupBy('m.id')
            ->orderBy('nbPlaylists', 'DESC')
            ->addOrderBy('m.releaseDate', 'DESC')
            ->setMaxResults($limit);

        return $this->filterByCatalogType($qb, $type)->getQuery()->getResult();
    }

    public function findLastWatchedBy(User $user, ?CatalogTypeEnum $type = null): ?Media
    {
        $qb = $this->createQueryBuilder('m')
            ->join('m.watchHistories', 'w')
            ->where('w.watcher = :user')
            ->setParameter('user', $user)
            ->orderBy('w.lastWatchedAt', 'DESC')
            ->setMaxResults(1);

        return $this->filterByCatalogType($qb, $type)->getQuery()->getOneOrNullResult();
    }

    public function findLatest(?CatalogTypeEnum $type = null): ?Media
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.releaseDate', 'DESC')
            ->setMaxResults(1);

        return $this->filterByCatalogType($qb, $type)->getQuery()->getOneOrNullResult();
    }

    /**
     * Médias les mieux notés selon un profil de goûts (poids par catégorie).
     *
     * @param array<int, float> $categoryWeights id de catégorie => poids
     * @param int[]             $excludedIds     médias déjà vus
     * @return Media[]
     */
    public function findByCategoryWeights(array $categoryWeights, array $excludedIds, ?CatalogTypeEnum $type, int $limit): array
    {
        if (!$categoryWeights) {
            return [];
        }

        // Les valeurs sont des int/float calculés côté serveur : on peut les écrire directement dans le CASE
        $cases = '';
        foreach ($categoryWeights as $categoryId => $weight) {
            $cases .= sprintf(' WHEN cat.id = %d THEN %.4F', $categoryId, $weight);
        }

        $qb = $this->createQueryBuilder('m')
            ->select('m, SUM(CASE' . $cases . ' ELSE 0 END) AS HIDDEN score')
            ->join('m.categories', 'cat')
            ->where('cat.id IN (:categories)')
            ->setParameter('categories', array_keys($categoryWeights))
            ->groupBy('m.id')
            ->orderBy('score', 'DESC')
            ->addOrderBy('m.releaseDate', 'DESC')
            ->setMaxResults($limit);

        if ($excludedIds) {
            $qb->andWhere('m.id NOT IN (:excluded)')->setParameter('excluded', $excludedIds);
        }

        return $this->filterByCatalogType($qb, $type)->getQuery()->getResult();
    }

    public function filterByCatalogType(QueryBuilder $qb, ?CatalogTypeEnum $type): QueryBuilder
    {
        if (!$type) {
            return $qb;
        }

        $alias = $qb->getRootAliases()[0];
        $isAnimation = sprintf(
            'EXISTS (SELECT anim.id FROM %s anim JOIN anim.medias animMedia WHERE animMedia = %s AND anim.nom = :animationCategory)',
            Category::class,
            $alias
        );
        $qb->setParameter('animationCategory', CatalogTypeEnum::ANIMATION_CATEGORY);

        return match ($type) {
            CatalogTypeEnum::ANIME => $qb->andWhere($isAnimation),
            CatalogTypeEnum::MOVIE => $qb->andWhere(sprintf('%s INSTANCE OF %s AND NOT %s', $alias, Movie::class, $isAnimation)),
            CatalogTypeEnum::SERIE => $qb->andWhere(sprintf('%s INSTANCE OF %s AND NOT %s', $alias, Serie::class, $isAnimation)),
        };
    }

}
