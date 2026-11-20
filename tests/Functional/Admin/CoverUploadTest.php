<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\AppWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CoverUploadTest extends AppWebTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $publicDir;
    private array $filesBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->publicDir = static::getContainer()->getParameter('kernel.project_dir') . '/public';
        $this->filesBefore = glob($this->publicDir . '/uploads/covers/*');
    }

    protected function tearDown(): void
    {
        // La base est remise à zéro par transaction, mais pas le disque : on supprime ce que le test a créé
        foreach (array_diff(glob($this->publicDir . '/uploads/covers/*'), $this->filesBefore) as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function png(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cover');
        file_put_contents($path, base64_decode(self::PNG));

        return $path;
    }

    private function editForm(int $mediaId)
    {
        return $this->client->request('GET', "/admin/movies/$mediaId/edit")->selectButton('Enregistrer les modifications')->form();
    }

    public function testUploadAndReplaceACover(): void
    {
        $id = $this->media('Akira')->getId();

        $form = $this->editForm($id);
        $form['cover_file']->upload($this->png());
        $this->client->submit($form);
        $first = $this->reload($this->media('Akira'))->getCoverImage();
        $this->assertMatchesRegularExpression('#^/uploads/covers/[a-f0-9]{32}\.png$#', $first);
        $this->assertFileExists($this->publicDir . $first);

        $this->client->request('GET', "/detail/$id");
        $this->assertStringContainsString($first, $this->client->getResponse()->getContent());

        // Remplacement : l'ancienne image est supprimée du disque
        $form = $this->editForm($id);
        $form['cover_file']->upload($this->png());
        $this->client->submit($form);
        $this->assertFileDoesNotExist($this->publicDir . $first);

        // Retour à une URL : l'image envoyée est supprimée aussi
        $second = $this->reload($this->media('Akira'))->getCoverImage();
        $this->client->submit($this->editForm($id), ['cover' => 'https://picsum.photos/seed/x/1920/1080']);
        $this->assertFileDoesNotExist($this->publicDir . $second);
    }

    public function testFakeImageIsRefused(): void
    {
        $fake = tempnam(sys_get_temp_dir(), 'fake');
        file_put_contents($fake, '<?php echo "pirate"; ?>');
        $form = $this->editForm($this->media('Akira')->getId());
        $form['cover_file']->upload($fake);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('p.text-red-600', 'Formats acceptés');
    }

    public function testUploadedImageIsKeptWhenAnotherFieldIsInvalid(): void
    {
        $form = $this->editForm($this->media('Akira')->getId());
        $form['cover_file']->upload($this->png());

        $this->client->submit($form, ['title' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertMatchesRegularExpression('#^/uploads/covers/#', $this->client->getCrawler()->filter('#cover-input')->attr('value'));
    }

    public function testDeletingAMediaDeletesItsUploadedCover(): void
    {
        $id = $this->media('Akira')->getId();
        $form = $this->editForm($id);
        $form['cover_file']->upload($this->png());
        $this->client->submit($form);
        $cover = $this->reload($this->media('Akira'))->getCoverImage();

        $crawler = $this->client->request('GET', '/admin/movies?q=akira');
        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());

        $this->assertFileDoesNotExist($this->publicDir . $cover);
    }
}
