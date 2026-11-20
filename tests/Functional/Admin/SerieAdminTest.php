<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\Serie;
use App\Tests\Functional\AppWebTestCase;

class SerieAdminTest extends AppWebTestCase
{
    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->serie = $this->media('Frieren');
    }

    private function openSeasons(): void
    {
        $this->client->request('GET', '/admin/series/' . $this->serie->getId() . '/seasons');
    }

    private function lastSeason(): Season
    {
        $this->em()->clear();
        $seasons = $this->em()->getRepository(Season::class)->findBy(['serie' => $this->serie->getId()], ['id' => 'ASC']);

        return end($seasons);
    }

    public function testAddASeasonWithAnAutomaticName(): void
    {
        $count = $this->serie->getSeasons()->count();
        $this->openSeasons();

        $this->client->submitForm('+ Ajouter une saison');
        $this->client->followRedirect();

        $this->assertSame('Saison ' . ($count + 1), $this->lastSeason()->getNumber());
    }

    public function testManageEpisodesOfASeason(): void
    {
        $this->openSeasons();
        $this->client->submitForm('+ Ajouter une saison', ['number' => 'Saison spéciale']);
        $crawler = $this->client->followRedirect();
        $section = $crawler->filter('main section')->last();

        // Ajout
        $this->client->submit($section->selectButton("+ Ajouter l'épisode")->form(), ['title' => 'Pilote', 'duration' => 42, 'released_at' => '2024-01-05']);
        $crawler = $this->client->followRedirect();
        $episode = $this->lastSeason()->getEpisodes()->first();
        $this->assertSame('Pilote', $episode->getTitle());
        $this->assertSame(42, $episode->getDuration());

        // Modification
        $this->client->submit($crawler->filter('main section')->last()->selectButton('Enregistrer')->form(), ['title' => 'Pilote (long)', 'duration' => 55]);
        $crawler = $this->client->followRedirect();
        $this->assertSame('Pilote (long)', $this->em()->find(Episode::class, $episode->getId())->getTitle());

        // Suppression
        $this->client->submit($crawler->filter('main section')->last()->filter('form[action$="/delete"]')->eq(1)->form());
        $this->client->followRedirect();
        $this->assertCount(0, $this->lastSeason()->getEpisodes());
    }

    public function testInvalidEpisodeIsNotSaved(): void
    {
        $this->openSeasons();
        $this->client->submitForm('+ Ajouter une saison');
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->filter('main section')->last()->selectButton("+ Ajouter l'épisode")->form(), ['title' => 'Sans durée', 'duration' => 0]);
        $this->client->followRedirect();

        $this->assertStringContainsString('Épisode non enregistré', $this->flashes());
        $this->assertCount(0, $this->lastSeason()->getEpisodes());
    }

    public function testDeletingASeasonDeletesItsEpisodes(): void
    {
        $season = $this->serie->getSeasons()->first();
        $episodeIds = $season->getEpisodes()->map(fn(Episode $episode) => $episode->getId())->toArray();
        $crawler = $this->client->request('GET', '/admin/series/' . $this->serie->getId() . '/seasons');

        $this->client->submit($crawler->filter('form[action$="/seasons/' . $season->getId() . '/delete"]')->form());

        $this->em()->clear();
        $this->assertNull($this->em()->find(Season::class, $season->getId()));
        $this->assertSame([], $this->em()->getRepository(Episode::class)->findBy(['id' => $episodeIds]));
    }

    public function testCannotEditAnEpisodeThroughAnotherSerie(): void
    {
        $otherEpisode = $this->media('Naruto')->getSeasons()->first()->getEpisodes()->first();
        $this->openSeasons();
        $token = $this->client->getCrawler()->filter('input[name=_token]')->attr('value');

        $this->client->request('POST', '/admin/series/' . $this->serie->getId() . '/episodes/' . $otherEpisode->getId() . '/delete', ['_token' => $token]);

        $this->assertResponseStatusCodeSame(404);
    }
}
