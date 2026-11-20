<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Category;
use App\Entity\Media;
use App\Tests\Functional\AppWebTestCase;

class CategoryAdminTest extends AppWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->client->followRedirects();
    }

    private function token(): string
    {
        return $this->client->request('GET', '/admin/categories')->filter('input[name=_token]')->attr('value');
    }

    public function testAddACategoryWithDefaultLabel(): void
    {
        $this->client->request('GET', '/admin/categories');
        $this->client->submitForm('+ Ajouter', ['nom' => 'Romance']);

        $this->assertStringContainsString('Catégorie « Romance » créée', $this->flashes());
        $this->assertSame('Romance', $this->category('Romance')->getLabel());
    }

    public function testNamesAreUniqueRegardlessOfCase(): void
    {
        $this->client->request('GET', '/admin/categories');
        $this->client->submitForm('+ Ajouter', ['nom' => 'action']);

        $this->assertStringContainsString('existe déjà', $this->flashes());
    }

    public function testEditTheLabel(): void
    {
        $drame = $this->category('Drame');

        $this->client->request('POST', '/admin/categories/' . $drame->getId() . '/edit', ['_token' => $this->token(), 'nom' => 'Drame', 'label' => 'Drames']);

        $this->assertSame('Drames', $this->reload($drame)->getLabel());
        $this->client->request('GET', '/discover');
        $this->assertSelectorTextContains('main', 'Drames');
    }

    public function testAnimationCannotBeRenamedOrDeleted(): void
    {
        $animation = $this->category('Animation');
        $token = $this->token();

        $this->client->request('POST', '/admin/categories/' . $animation->getId() . '/edit', ['_token' => $token, 'nom' => 'Dessins animés', 'label' => 'x']);
        $this->assertStringContainsString('seul son label peut être modifié', $this->flashes());

        $this->client->request('POST', '/admin/categories/' . $animation->getId() . '/delete', ['_token' => $token]);
        $this->assertStringContainsString('ne peut pas être supprimée', $this->flashes());

        $this->assertSame('Animation', $this->reload($animation)->getNom());
    }

    public function testDeletingACategoryKeepsItsMedias(): void
    {
        $horror = $this->category('Horreur');
        $mediaCount = $this->em()->getRepository(Media::class)->count([]);

        $this->client->request('POST', '/admin/categories/' . $horror->getId() . '/delete', ['_token' => $this->token()]);

        $this->em()->clear();
        $this->assertNull($this->em()->find(Category::class, $horror->getId()));
        $this->assertSame($mediaCount, $this->em()->getRepository(Media::class)->count([]));
    }
}
