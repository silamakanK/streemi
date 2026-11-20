<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Media;
use App\Entity\Movie;
use App\Entity\Serie;
use App\Enum\CatalogTypeEnum;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaRepositoryTest extends KernelTestCase
{
    private MediaRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get(MediaRepository::class);
    }

    private static function isAnime(Media $media): bool
    {
        foreach ($media->getCategories() as $category) {
            if ($category->getNom() === CatalogTypeEnum::ANIMATION_CATEGORY) {
                return true;
            }
        }

        return false;
    }

    public function testAnimeTabContainsOnlyTheAnimationCategory(): void
    {
        $animes = iterator_to_array($this->repository->findForAdmin('', CatalogTypeEnum::ANIME, 1, 100));

        $this->assertCount(24, $animes, 'Les fixtures contiennent 24 animés');
        foreach ($animes as $media) {
            $this->assertTrue(self::isAnime($media), "{$media->getTitle()} devrait être un animé");
        }
    }

    public function testMovieAndSerieTabsExcludeAnimes(): void
    {
        $cases = [[CatalogTypeEnum::MOVIE, Movie::class], [CatalogTypeEnum::SERIE, Serie::class]];
        foreach ($cases as [$type, $class]) {
            foreach ($this->repository->findPopular($type, 100) as $media) {
                $this->assertInstanceOf($class, $media);
                $this->assertFalse(self::isAnime($media), "{$media->getTitle()} ne devrait pas être dans {$type->label()}");
            }
        }
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $titles = array_map(fn(Media $media) => $media->getTitle(), $this->repository->search('NARUTO'));

        $this->assertSame(['Naruto'], $titles);
    }

    public function testWeightedRecommendationsExcludeAlreadySeenMedias(): void
    {
        $horror = static::getContainer()->get('doctrine')->getRepository(\App\Entity\Category::class)->findOneBy(['nom' => 'Horreur']);
        $seen = $this->repository->findOneBy(['title' => 'Demon Slayer']);

        $results = $this->repository->findByCategoryWeights([$horror->getId() => 1.0], [$seen->getId()], CatalogTypeEnum::ANIME, 10);

        $this->assertNotEmpty($results);
        foreach ($results as $media) {
            $this->assertNotSame($seen->getId(), $media->getId());
            $this->assertContains('Horreur', $media->getCategories()->map(fn($c) => $c->getNom())->toArray());
        }
    }
}
