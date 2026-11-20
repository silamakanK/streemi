<?php

declare(strict_types=1);

namespace App\Tests\Functional;

class AssistantTest extends AppWebTestCase
{
    private function token(): string
    {
        $html = $this->client->request('GET', '/')->html();
        preg_match("/const csrf = '([^']+)'/", $html, $match);

        return $match[1];
    }

    private function send(string $message, ?string $token): void
    {
        $this->client->request('POST', '/assistant/message', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => (string) $token], content: json_encode(['message' => $message]));
    }

    public function testVisitorsSeeALoginInvitation(): void
    {
        $this->client->request('GET', '/');

        $this->assertSelectorTextContains('#assistant-panel', 'Connecte-toi');
        $this->assertSelectorNotExists('#assistant-form');
    }

    public function testVisitorsCannotCallTheAssistant(): void
    {
        $this->send('Bonjour', 'x');

        $this->assertResponseRedirects('/login');
    }

    public function testRequiresTheCsrfToken(): void
    {
        $this->loginAs('demo@example.com');

        $this->send('Un animé', 'faux-token');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testExplainsWhenTheApiKeyIsMissing(): void
    {
        // GEMINI_API_KEY n'est pas définie en environnement de test : aucun appel à Google
        $this->loginAs('demo@example.com');

        $this->send('Un animé culte', $this->token());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('GEMINI_API_KEY', $data['reply']);
        $this->assertSame([], $data['medias']);
    }

    public function testEmptyMessageIsRefused(): void
    {
        $this->loginAs('demo@example.com');

        $this->send('   ', $this->token());

        $this->assertResponseStatusCodeSame(400);
    }

    public function testConversationCanBeReset(): void
    {
        $this->loginAs('demo@example.com');
        $token = $this->token();
        $this->send('Un animé', $token);

        $this->client->request('POST', '/assistant/reset', server: ['HTTP_X_CSRF_TOKEN' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->client->getRequest()->getSession()->get('assistant_history'));
    }
}
