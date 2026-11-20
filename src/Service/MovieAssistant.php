<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Entity\Movie;
use App\Entity\User;
use App\Enum\CatalogTypeEnum;
use App\Repository\MediaRepository;
use App\Repository\WatchHistoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Assistant conversationnel : l'utilisateur décrit ses envies, Google Gemini propose des médias du catalogue.
 *
 * Un seul appel à l'API par message. La réponse est structurée (texte + identifiants de médias),
 * et les identifiants sont revérifiés côté serveur : l'assistant ne peut proposer que ce qui existe.
 */
class MovieAssistant
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const MAX_SUGGESTIONS = 4;

    private const INSTRUCTIONS = <<<'TXT'
        Tu es l'assistant de Streemi, une plateforme de streaming de films, séries et animés.
        Ton rôle : aider l'utilisateur à trouver quoi regarder à partir de ses envies, de son humeur, de ses loisirs,
        du temps dont il dispose ou de ce qu'il a aimé.

        Règles :
        - Réponds toujours en français, sur un ton chaleureux et naturel, en 2 à 4 phrases maximum.
        - Ne propose QUE des médias du catalogue ci-dessous, en renvoyant leurs identifiants dans media_ids.
          N'invente jamais un titre qui n'y figure pas. Si rien ne correspond vraiment, dis-le honnêtement.
        - Propose entre 1 et 4 médias, et explique en une phrase pourquoi chacun correspond à la demande.
        - Si la demande est trop vague pour bien choisir, pose une seule question courte (genre, humeur, film ou série,
          seul ou à plusieurs…) et laisse media_ids vide.
        - Tiens compte de l'historique de visionnage de l'utilisateur s'il est fourni, sans reproposer ce qu'il a déjà vu
          sauf s'il le demande.
        - Si on te demande autre chose que des recommandations, ramène gentiment la conversation vers les films,
          séries et animés.
        - Réponds uniquement avec un objet JSON de la forme {"reply": "texte affiché", "media_ids": [12, 34]}.
        TXT;

    public function __construct(
        private MediaRepository $mediaRepository,
        private WatchHistoryRepository $watchHistoryRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'GEMINI_API_KEY')] private string $apiKey,
        #[Autowire(env: 'GEMINI_MODEL')] private string $model,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param array<int, array{role: string, content: string}> $history conversation jusqu'au dernier message utilisateur inclus
     * @return array{reply: string, medias: Media[]}
     */
    public function ask(array $history, ?User $user): array
    {
        if (!$this->isConfigured()) {
            return $this->failure('L\'assistant n\'est pas encore configuré (GEMINI_API_KEY manquante dans .env.local).');
        }

        $catalog = [];
        foreach ($this->mediaRepository->findBy([], ['id' => 'ASC']) as $media) {
            $catalog[$media->getId()] = $media;
        }

        // Gemini nomme « model » le rôle de l'assistant
        $contents = array_map(fn(array $message) => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $history);

        try {
            $response = $this->httpClient->request('POST', sprintf(self::ENDPOINT, $this->model), [
                'headers' => ['x-goog-api-key' => $this->apiKey],
                'json' => [
                    'systemInstruction' => ['parts' => [
                        ['text' => self::INSTRUCTIONS . "\n\n" . $this->describeCatalog($catalog) . "\n\n" . $this->describeUser($user)],
                    ]],
                    'contents' => $contents,
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'reply' => ['type' => 'string', 'description' => 'Réponse affichée à l\'utilisateur'],
                                'media_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Identifiants des médias proposés, dans l\'ordre de préférence'],
                            ],
                            'required' => ['reply', 'media_ids'],
                        ],
                    ],
                ],
                'timeout' => 30,
            ]);
            $status = $response->getStatusCode();
            $body = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Assistant : API Gemini injoignable', ['exception' => $e]);
            return $this->failure('Impossible de joindre l\'assistant, vérifie ta connexion.');
        }

        if ($status === 429) {
            return $this->failure('Le quota gratuit de l\'assistant est atteint pour le moment, réessaie un peu plus tard.');
        }
        if (in_array($status, [400, 401, 403], true) && str_contains(json_encode($body), 'API key')) {
            $this->logger->error('Assistant : clé API Gemini invalide', ['response' => $body]);
            return $this->failure('L\'assistant est mal configuré (clé API invalide).');
        }
        if ($status >= 400) {
            $this->logger->error('Assistant : erreur de l\'API Gemini', ['status' => $status, 'response' => $body]);
            return $this->failure('L\'assistant a rencontré un problème, réessaie dans un instant.');
        }

        // Demande bloquée par les filtres de sécurité de Google
        $candidate = $body['candidates'][0] ?? null;
        if (isset($body['promptFeedback']['blockReason']) || !$candidate || ($candidate['finishReason'] ?? 'STOP') !== 'STOP') {
            return $this->failure('Je ne peux pas répondre à cette demande. Parle-moi plutôt de ce que tu as envie de regarder !');
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            // Les parties « thought » sont le raisonnement interne du modèle, pas la réponse
            if (isset($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }
        // Par sécurité, on retire d'éventuelles balises ```json autour de la réponse
        $data = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $text)), true);
        if (!is_array($data) || !isset($data['reply'])) {
            $this->logger->warning('Assistant : réponse Gemini illisible', ['text' => $text]);
            return $this->failure('Je n\'ai pas bien compris, peux-tu reformuler ?');
        }

        // On ne garde que des identifiants qui existent vraiment, sans doublon
        $medias = [];
        foreach (array_unique($data['media_ids'] ?? []) as $id) {
            if (isset($catalog[$id]) && count($medias) < self::MAX_SUGGESTIONS) {
                $medias[] = $catalog[$id];
            }
        }

        return ['reply' => $data['reply'], 'medias' => $medias];
    }

    public static function typeLabel(Media $media): string
    {
        foreach ($media->getCategories() as $category) {
            if ($category->getNom() === CatalogTypeEnum::ANIMATION_CATEGORY) {
                return 'Animé';
            }
        }

        return $media instanceof Movie ? 'Film' : 'Série';
    }

    /** @param array<int, Media> $catalog */
    private function describeCatalog(array $catalog): string
    {
        $lines = ['Catalogue (id | type | titre | année | genres | résumé) :'];
        foreach ($catalog as $id => $media) {
            $genres = [];
            foreach ($media->getCategories() as $category) {
                if ($category->getNom() !== CatalogTypeEnum::ANIMATION_CATEGORY) {
                    $genres[] = $category->getNom();
                }
            }
            $lines[] = sprintf(
                '%d | %s | %s | %s | %s | %s',
                $id,
                self::typeLabel($media),
                $media->getTitle(),
                $media->getReleaseDate()?->format('Y') ?? '?',
                implode(', ', $genres) ?: '-',
                $media->getShortDescription()
            );
        }

        return implode("\n", $lines);
    }

    private function describeUser(?User $user): string
    {
        if (!$user) {
            return 'Utilisateur non connecté : aucun historique disponible.';
        }

        $titles = [];
        foreach (array_slice($this->watchHistoryRepository->findByWatcherOrderedByDate($user->getId()), 0, 10) as $entry) {
            $titles[] = sprintf('%s (id %d)', $entry->getMedia()->getTitle(), $entry->getMedia()->getId());
        }

        return sprintf(
            'Utilisateur : %s. Derniers médias regardés : %s.',
            $user->getUsername(),
            $titles ? implode(', ', $titles) : 'aucun pour l\'instant'
        );
    }

    /** @return array{reply: string, medias: Media[]} */
    private function failure(string $reply): array
    {
        return ['reply' => $reply, 'medias' => []];
    }
}
