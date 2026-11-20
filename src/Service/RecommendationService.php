<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Entity\User;
use App\Enum\CatalogTypeEnum;
use App\Repository\MediaRepository;
use App\Repository\WatchHistoryRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Recommande des médias selon ce que l'utilisateur a regardé et consulté récemment.
 *
 * 1. On récupère les derniers médias regardés (historique, poids fort) et consultés (fiches ouvertes, poids faible).
 * 2. Chaque signal compte d'autant plus qu'il est récent (décroissance exponentielle) et souvent revu.
 * 3. On en déduit un profil de goûts : un score par catégorie, et par langue.
 * 4. Les médias pas encore vus sont notés selon ce profil, et on garde les meilleurs.
 */
class RecommendationService
{
    private const SESSION_KEY = 'recently_viewed_medias';
    private const MAX_SIGNALS = 10;
    private const WATCHED_WEIGHT = 2.0;
    private const VIEWED_WEIGHT = 1.0;
    private const RECENCY_DECAY = 0.85;
    private const LANGUAGE_WEIGHT = 0.3;

    public function __construct(
        private MediaRepository $mediaRepository,
        private WatchHistoryRepository $watchHistoryRepository,
        private RequestStack $requestStack,
    ) {}

    /** Mémorise en session la fiche consultée (fonctionne aussi pour les visiteurs non connectés). */
    public function recordView(Media $media): void
    {
        $session = $this->requestStack->getSession();
        $ids = array_diff($session->get(self::SESSION_KEY, []), [$media->getId()]);
        array_unshift($ids, $media->getId());
        $session->set(self::SESSION_KEY, array_slice($ids, 0, self::MAX_SIGNALS * 2));
    }

    /**
     * @return array{medias: Media[], topCategories: string[]}
     */
    public function recommend(?User $user, ?CatalogTypeEnum $type = null, int $limit = 6): array
    {
        $signals = $this->collectSignals($user);
        if (!$signals) {
            return ['medias' => [], 'topCategories' => []];
        }

        $categoryWeights = [];
        $categoryNames = [];
        $languageWeights = [];
        foreach ($signals as ['media' => $media, 'weight' => $weight]) {
            $categories = $media->getCategories();
            foreach ($categories as $category) {
                // Un média avec beaucoup de catégories ne doit pas peser plus qu'un autre
                $categoryWeights[$category->getId()] = ($categoryWeights[$category->getId()] ?? 0) + $weight / count($categories);
                $categoryNames[$category->getId()] = $category->getNom();
            }
            $languages = $media->getLanguages();
            foreach ($languages as $language) {
                $languageWeights[$language->getId()] = ($languageWeights[$language->getId()] ?? 0) + $weight / count($languages);
            }
        }

        arsort($categoryWeights);
        $excludedIds = array_map(fn(array $signal) => $signal['media']->getId(), $signals);

        // Présélection en SQL sur les catégories, puis affinage avec les langues
        $candidates = $this->mediaRepository->findByCategoryWeights($categoryWeights, $excludedIds, $type, $limit * 4);

        $scored = [];
        foreach ($candidates as $candidate) {
            $score = 0.0;
            foreach ($candidate->getCategories() as $category) {
                $score += $categoryWeights[$category->getId()] ?? 0;
            }
            foreach ($candidate->getLanguages() as $language) {
                $score += self::LANGUAGE_WEIGHT * ($languageWeights[$language->getId()] ?? 0);
            }
            $scored[] = ['media' => $candidate, 'score' => $score];
        }
        usort($scored, fn(array $a, array $b) => $b['score'] <=> $a['score']);

        return [
            'medias' => array_column(array_slice($scored, 0, $limit), 'media'),
            'topCategories' => array_map(fn(int $id) => $categoryNames[$id], array_slice(array_keys($categoryWeights), 0, 3)),
        ];
    }

    /**
     * @return array<int, array{media: Media, weight: float}> indexé par id de média
     */
    private function collectSignals(?User $user): array
    {
        $signals = [];

        if ($user) {
            $history = array_slice($this->watchHistoryRepository->findByWatcherOrderedByDate($user->getId()), 0, self::MAX_SIGNALS);
            foreach ($history as $rank => $entry) {
                $media = $entry->getMedia();
                $weight = self::WATCHED_WEIGHT * self::RECENCY_DECAY ** $rank * (1 + log(max(1, $entry->getNumberOfViews())));
                $signals[$media->getId()] = ['media' => $media, 'weight' => $weight];
            }
        }

        $viewedIds = array_slice($this->requestStack->getSession()->get(self::SESSION_KEY, []), 0, self::MAX_SIGNALS);
        $viewed = $viewedIds ? $this->mediaRepository->findBy(['id' => $viewedIds]) : [];
        $ranks = array_flip($viewedIds);
        foreach ($viewed as $media) {
            $weight = self::VIEWED_WEIGHT * self::RECENCY_DECAY ** $ranks[$media->getId()];
            $signals[$media->getId()] = [
                'media' => $media,
                'weight' => ($signals[$media->getId()]['weight'] ?? 0) + $weight,
            ];
        }

        return $signals;
    }
}
