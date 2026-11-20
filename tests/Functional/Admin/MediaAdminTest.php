<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Media;
use App\Entity\Movie;
use App\Entity\Serie;
use App\Tests\Functional\AppWebTestCase;

class MediaAdminTest extends AppWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    /** Valeurs valides du formulaire de création, à surcharger */
    private function validValues(array $overrides = []): array
    {
        $crawler = $this->client->request('GET', '/admin/movies/add');
        $values = $crawler->selectButton('Créer')->form()->getPhpValues();

        return array_merge($values, [
            'title' => 'Mon film',
            'type' => 'movie',
            'short_description' => 'Courte',
            'long_description' => 'Longue',
            'cover' => 'https://picsum.photos/seed/test/1920/1080',
            'release_date' => '2025-03-01',
            'categories' => [$this->category('Drame')->getId()],
            'languages' => [],
            'staff' => [['name' => 'Ada', 'role' => 'Réalisatrice', 'image' => '']],
            'casting' => [['name' => '', 'role' => '', 'image' => '']],
        ], $overrides);
    }

    private function titles(): array
    {
        return $this->client->getCrawler()->filter('tbody td p.font-medium')->each(fn($node) => $node->text());
    }

    public function testListIsPaginated(): void
    {
        $crawler = $this->client->request('GET', '/admin/movies');

        $this->assertCount(10, $crawler->filter('tbody tr'));
        $this->assertSelectorTextContains('main', '124 résultats');

        $this->client->request('GET', '/admin/movies?page=13');
        $this->assertCount(4, $this->client->getCrawler()->filter('tbody tr'));
    }

    public function testSearchAndFilters(): void
    {
        $this->client->request('GET', '/admin/movies?q=naruto');
        $this->assertSame(['Naruto'], $this->titles());

        $this->client->request('GET', '/admin/movies?type=anime');
        $this->assertSelectorTextContains('main', '24 résultats');

        $horror = $this->category('Horreur');
        $this->client->request('GET', '/admin/movies?category=' . $horror->getId());
        $this->assertSelectorTextContains('main', count($horror->getMedias()) . ' résultat');
    }

    public function testInvalidFormShowsEveryError(): void
    {
        $this->client->request('POST', '/admin/movies/add', $this->validValues([
            'title' => '', 'short_description' => '', 'long_description' => '', 'cover' => 'pas-une-url', 'release_date' => '', 'categories' => [],
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertCount(6, $this->client->getCrawler()->filter('p.text-red-600'));
    }

    public function testCreateAMovie(): void
    {
        $this->client->request('POST', '/admin/movies/add', $this->validValues());

        $this->assertResponseRedirects('/admin/movies');
        $movie = $this->media('Mon film');
        $this->assertInstanceOf(Movie::class, $movie);
        $this->assertSame('2025-03-01', $movie->getReleaseDate()->format('Y-m-d'));
        $this->assertSame([['name' => 'Ada', 'role' => 'Réalisatrice', 'image' => '']], $movie->getStaff());
        $this->assertSame([], $movie->getCasting(), 'Les lignes sans nom sont ignorées');
    }

    public function testCreatingASerieLeadsToItsSeasons(): void
    {
        $this->client->request('POST', '/admin/movies/add', $this->validValues(['title' => 'Ma série', 'type' => 'serie']));

        $serie = $this->media('Ma série');
        $this->assertInstanceOf(Serie::class, $serie);
        $this->assertResponseRedirects('/admin/series/' . $serie->getId() . '/seasons');
    }

    public function testCheckingAnimationPutsTheMediaInTheAnimeTab(): void
    {
        $this->client->request('POST', '/admin/movies/add', $this->validValues([
            'title' => 'Nouvel animé', 'categories' => [$this->category('Animation')->getId()],
        ]));

        $this->client->request('GET', '/admin/movies?type=anime&q=nouvel');
        $this->assertSame(['Nouvel animé'], $this->titles());
    }

    public function testEditAMedia(): void
    {
        $akira = $this->media('Akira');
        $crawler = $this->client->request('GET', '/admin/movies/' . $akira->getId() . '/edit');

        $this->client->submit($crawler->selectButton('Enregistrer les modifications')->form(), ['title' => 'Akira (version restaurée)']);

        $this->assertResponseRedirects('/admin/movies');
        $this->assertSame('Akira (version restaurée)', $this->reload($akira)->getTitle());
    }

    public function testDeletingASerieAlsoDeletesItsSeasonsAndEpisodes(): void
    {
        $naruto = $this->media('Naruto');
        $this->assertGreaterThan(0, $naruto->getSeasons()->count());
        $crawler = $this->client->request('GET', '/admin/movies?q=naruto');

        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());

        $this->assertResponseRedirects('/admin/movies');
        $this->em()->clear();
        $this->assertNull($this->em()->getRepository(Media::class)->findOneBy(['title' => 'Naruto']));
    }

    public function testDeletingRequiresTheCsrfToken(): void
    {
        $akira = $this->media('Akira');

        $this->client->request('POST', '/admin/movies/' . $akira->getId() . '/delete');

        $this->assertNotNull($this->reload($akira));
    }
}
