<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\MediaRepository;
use App\Repository\WatchHistoryRepository;
use App\Service\MovieAssistant;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** L'API Gemini est remplacée par des réponses simulées : aucun appel réseau, aucune clé nécessaire. */
class MovieAssistantTest extends KernelTestCase
{
    private ?array $sentRequest = null;

    private function assistant(MockResponse $response, string $apiKey = 'cle-de-test'): MovieAssistant
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response) {
            $this->sentRequest = ['url' => $url, 'options' => $options];

            return $response;
        });
        $container = static::getContainer();

        return new MovieAssistant(
            $container->get(MediaRepository::class),
            $container->get(WatchHistoryRepository::class),
            $http,
            new NullLogger(),
            $apiKey,
            'gemini-test',
        );
    }

    private static function geminiAnswer(array $data, array $extraParts = []): MockResponse
    {
        return new MockResponse(json_encode(['candidates' => [[
            'finishReason' => 'STOP',
            'content' => ['parts' => [...$extraParts, ['text' => json_encode($data)]]],
        ]]]));
    }

    private function user(): User
    {
        return static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email' => 'demo@example.com']);
    }

    private function mediaId(string $title): int
    {
        return static::getContainer()->get(MediaRepository::class)->findOneBy(['title' => $title])->getId();
    }

    public function testReturnsTheSuggestedMediasAndIgnoresUnknownOrDuplicateIds(): void
    {
        $naruto = $this->mediaId('Naruto');
        $dbz = $this->mediaId('Dragon Ball Z');

        $answer = $this->assistant(self::geminiAnswer(
            ['reply' => 'Deux classiques !', 'media_ids' => [$naruto, 999999, $dbz, $naruto]],
            [['text' => 'raisonnement interne', 'thought' => true]],
        ))->ask([['role' => 'user', 'content' => 'Un animé culte']], $this->user());

        $this->assertSame('Deux classiques !', $answer['reply']);
        $this->assertSame(['Naruto', 'Dragon Ball Z'], array_map(fn($media) => $media->getTitle(), $answer['medias']));
    }

    public function testSendsTheConversationWithGeminiRolesAndTheCatalog(): void
    {
        $this->assistant(self::geminiAnswer(['reply' => 'ok', 'media_ids' => []]))->ask([
            ['role' => 'user', 'content' => 'Un animé'],
            ['role' => 'assistant', 'content' => 'Film ou série ?'],
            ['role' => 'user', 'content' => 'Série'],
        ], $this->user());

        $body = json_decode($this->sentRequest['options']['body'], true);
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent', $this->sentRequest['url']);
        $this->assertContains('x-goog-api-key: cle-de-test', $this->sentRequest['options']['headers']);
        $this->assertSame(['user', 'model', 'user'], array_column($body['contents'], 'role'));
        $this->assertStringContainsString('Le Voyage de Chihiro', $body['systemInstruction']['parts'][0]['text']);
    }

    public function testReadsJsonWrappedInMarkdownFences(): void
    {
        $response = new MockResponse(json_encode(['candidates' => [[
            'finishReason' => 'STOP',
            'content' => ['parts' => [['text' => "```json\n{\"reply\": \"Voilà\", \"media_ids\": []}\n```"]]],
        ]]]));

        $this->assertSame('Voilà', $this->assistant($response)->ask([['role' => 'user', 'content' => 'x']], null)['reply']);
    }

    /** @dataProvider failures */
    public function testFailuresBecomeFriendlyMessages(MockResponse $response, string $expected): void
    {
        $answer = $this->assistant($response)->ask([['role' => 'user', 'content' => 'x']], null);

        $this->assertStringContainsString($expected, $answer['reply']);
        $this->assertSame([], $answer['medias']);
    }

    public static function failures(): array
    {
        return [
            'quota atteint' => [new MockResponse('{"error":{"code":429}}', ['http_code' => 429]), 'quota gratuit'],
            'clé invalide' => [new MockResponse('{"error":{"message":"API key not valid"}}', ['http_code' => 400]), 'clé API invalide'],
            'erreur serveur' => [new MockResponse('{}', ['http_code' => 500]), 'problème'],
            'demande bloquée' => [new MockResponse('{"promptFeedback":{"blockReason":"SAFETY"}}'), 'ne peux pas répondre'],
            'réponse illisible' => [self::geminiAnswer(['autre' => 'chose']), 'reformuler'],
        ];
    }

    public function testDoesNotCallTheApiWithoutKey(): void
    {
        $assistant = $this->assistant(self::geminiAnswer(['reply' => 'ok', 'media_ids' => []]), apiKey: '');

        $this->assertStringContainsString('GEMINI_API_KEY', $assistant->ask([['role' => 'user', 'content' => 'x']], null)['reply']);
        $this->assertNull($this->sentRequest);
    }
}
