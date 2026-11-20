<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Media;

class RecommendationTest extends AppWebTestCase
{
    /** Identifiants des médias de la section « Recommandé pour vous » */
    private function recommendedIds(): array
    {
        $section = $this->client->getCrawler()->filter('section')->reduce(fn($node) => str_contains($node->text(), 'Recommandé pour vous'));
        if ($section->count() === 0) {
            return [];
        }

        return $section->filter('a[href^="/detail/"]')->each(fn($link) => (int) basename($link->attr('href')));
    }

    public function testNoRecommendationsForANewVisitor(): void
    {
        $this->client->request('GET', '/');

        $this->assertSelectorTextNotContains('main', 'Recommandé pour vous');
    }

    public function testViewedPagesDriveTheRecommendations(): void
    {
        $viewed = ['Demon Slayer', 'Jujutsu Kaisen', 'Chainsaw Man'];
        $viewedIds = [];
        foreach ($viewed as $title) {
            $viewedIds[] = $id = $this->media($title)->getId();
            $this->client->request('GET', "/detail/$id");
        }

        $this->client->request('GET', '/?type=anime');

        $this->assertSelectorTextContains('main', 'Parce que vous aimez');
        $ids = $this->recommendedIds();
        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertNotContains($id, $viewedIds, 'Un média déjà consulté ne doit pas être recommandé');
            $media = $this->em()->find(Media::class, $id);
            $this->assertContains('Animation', $media->getCategories()->map(fn($c) => $c->getNom())->toArray(), 'Les recommandations respectent l\'onglet');
        }
    }

    public function testWatchedMediaIsUsedForLoggedInUsers(): void
    {
        $this->loginAs('demo@example.com');
        $akira = $this->media('Akira');
        $crawler = $this->client->request('GET', '/detail/' . $akira->getId());
        $this->client->submit($crawler->filter('form[action$="/watch/' . $akira->getId() . '"]')->form());

        $this->client->request('GET', '/?type=anime');

        // Le bandeau propose de reprendre le dernier animé regardé
        $this->assertSelectorTextContains('main', 'Reprendre la lecture');
        $this->assertSelectorTextContains('main', 'Akira');
    }
}
