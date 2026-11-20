<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Playlist;

class PlaylistTest extends AppWebTestCase
{
    private function createPlaylist(string $name): Playlist
    {
        $this->client->request('GET', '/lists');
        $this->client->submitForm('Créer', ['name' => $name]);

        return $this->em()->getRepository(Playlist::class)->findOneBy(['name' => $name]);
    }

    /** Ajoute un média depuis sa fiche, comme le fait le bouton « Ajouter » */
    private function addFromDetailPage(Playlist $playlist, int $mediaId): void
    {
        $html = $this->client->request('GET', "/detail/$mediaId")->html();
        preg_match("/token\\.value = '([^']+)'/", $html, $token);
        $this->client->request('POST', "/lists/{$playlist->getId()}/add/$mediaId", ['redirect' => 'detail', '_token' => $token[1]]);
    }

    public function testCreateAPlaylistAndAddAMedia(): void
    {
        $this->loginAs('demo@example.com');
        $playlist = $this->createPlaylist('Soirée animés');
        $this->assertNotNull($playlist);

        $naruto = $this->media('Naruto')->getId();
        $this->addFromDetailPage($playlist, $naruto);
        $this->assertResponseRedirects("/detail/$naruto");

        $this->client->request('GET', '/lists?playlist=' . $playlist->getId());
        $this->assertSelectorTextContains('main', 'Naruto');
    }

    public function testCreatingAPlaylistRequiresTheCsrfToken(): void
    {
        $this->loginAs('demo@example.com');

        $this->client->request('POST', '/lists/create', ['name' => 'Sans token']);

        $this->assertNull($this->em()->getRepository(Playlist::class)->findOneBy(['name' => 'Sans token']));
    }

    public function testAddingAMediaRequiresTheCsrfToken(): void
    {
        $this->loginAs('demo@example.com');
        $playlist = $this->createPlaylist('Ma liste');

        $this->client->request('POST', "/lists/{$playlist->getId()}/add/" . $this->media('Naruto')->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSomeoneElsesPlaylistIsPrivate(): void
    {
        $this->loginAs('test_0@example.com');
        $playlist = $this->createPlaylist('Liste privée');

        $this->loginAs('demo@example.com');
        $this->client->request('GET', '/lists?playlist=' . $playlist->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCannotAddToSomeoneElsesPlaylist(): void
    {
        $this->loginAs('test_0@example.com');
        $playlist = $this->createPlaylist('Pas à toi');

        $this->loginAs('demo@example.com');
        $this->client->request('POST', "/lists/{$playlist->getId()}/add/" . $this->media('Naruto')->getId());

        $this->assertResponseStatusCodeSame(404);
    }
}
