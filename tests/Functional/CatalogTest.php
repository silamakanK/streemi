<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Movie;
use App\Entity\Serie;

class CatalogTest extends AppWebTestCase
{
    /** Titres des cartes « Tendances » de la page courante */
    private function cardTitles(): array
    {
        return $this->client->getCrawler()->filter('ul li a span.capitalize')->each(
            fn($node) => trim(preg_replace('/^#\d+\s*-\s*/', '', $node->text()))
        );
    }

    public function testSeriesTabShowsOnlySeries(): void
    {
        $this->client->request('GET', '/?type=serie');

        $this->assertSelectorTextContains('nav.text-gray-400 a.font-semibold', 'Séries');
        $titles = $this->cardTitles();
        $this->assertNotEmpty($titles);
        foreach ($titles as $title) {
            $this->assertInstanceOf(Serie::class, $this->media($title), "$title devrait être une série");
        }
    }

    public function testMoviesIsTheDefaultTab(): void
    {
        $this->client->request('GET', '/');

        $this->assertSelectorTextContains('nav.text-gray-400 a.font-semibold', 'Films');
        foreach ($this->cardTitles() as $title) {
            $this->assertInstanceOf(Movie::class, $this->media($title), "$title devrait être un film");
        }
    }

    public function testAnimeTabShowsOnlyAnimes(): void
    {
        $this->client->request('GET', '/?type=anime');

        $this->assertSelectorTextContains('nav.text-gray-400 a.font-semibold', 'Animés');
        // Le bandeau affiche l'animé le plus récent des fixtures
        $this->assertSelectorTextContains('main', 'Dandadan');
        foreach ($this->cardTitles() as $title) {
            $categories = $this->media($title)->getCategories()->map(fn($category) => $category->getNom())->toArray();
            $this->assertContains('Animation', $categories, "$title devrait être un animé");
        }
    }

    public function testUnknownTabFallsBackToMovies(): void
    {
        $this->client->request('GET', '/?type=nimporte');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav.text-gray-400 a.font-semibold', 'Films');
    }

    public function testSearchFindsATitle(): void
    {
        $this->client->request('GET', '/search?q=chihiro');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Le Voyage de Chihiro');
    }

    public function testDetailPageShowsSeasonsOfASerie(): void
    {
        $this->client->request('GET', '/detail/' . $this->media('Naruto')->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Saison 1');
    }
}
