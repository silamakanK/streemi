<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Comment;
use App\Entity\WatchHistory;
use App\Enum\CommentStatusEnum;

class MediaPageTest extends AppWebTestCase
{
    public function testWatchingAddsTheMediaToTheHistory(): void
    {
        $user = $this->loginAs('demo@example.com');
        $media = $this->media('Your Name');
        $crawler = $this->client->request('GET', '/detail/' . $media->getId());

        $this->client->submit($crawler->filter('form[action$="/watch/' . $media->getId() . '"]')->form());

        $entry = $this->em()->getRepository(WatchHistory::class)->findOneBy(['watcher' => $user, 'media' => $media]);
        $this->assertSame(1, $entry->getNumberOfViews());
        $this->client->request('GET', '/history');
        $this->assertSelectorTextContains('main', 'Your Name');
    }

    public function testWatchingRequiresTheCsrfToken(): void
    {
        $user = $this->loginAs('demo@example.com');
        $media = $this->media('Your Name');

        $this->client->request('POST', '/watch/' . $media->getId());

        $this->assertNull($this->em()->getRepository(WatchHistory::class)->findOneBy(['watcher' => $user, 'media' => $media]));
    }

    public function testCommentsWaitForModeration(): void
    {
        $this->loginAs('demo@example.com');
        $media = $this->media('Suzume');
        $this->client->request('GET', '/detail/' . $media->getId());

        $this->client->submitForm('Envoyer', ['content' => 'Magnifique !']);
        $this->client->followRedirect();

        $comment = $this->em()->getRepository(Comment::class)->findOneBy(['content' => 'Magnifique !']);
        $this->assertSame(CommentStatusEnum::WAITING, $comment->getStatus());
        $this->assertStringContainsString('après modération', $this->flashes());
        $this->assertSelectorTextNotContains('main ul', 'Magnifique !');
    }

    public function testCommentRequiresTheCsrfToken(): void
    {
        $this->loginAs('demo@example.com');

        $this->client->request('POST', '/detail/' . $this->media('Suzume')->getId() . '/comment', ['content' => 'Spam']);

        $this->assertNull($this->em()->getRepository(Comment::class)->findOneBy(['content' => 'Spam']));
    }
}
