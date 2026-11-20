<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CoverUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CoverUploaderTest extends TestCase
{
    // Image PNG valide de 1×1 pixel
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $directory;
    private CoverUploader $uploader;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/streemi-covers-' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->directory);
        $this->uploader = new CoverUploader($this->directory, $this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    private function file(string $content, string $originalName): UploadedFile
    {
        $path = $this->directory . '/source-' . bin2hex(random_bytes(4));
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, null, null, true);
    }

    public function testAcceptsARealImage(): void
    {
        $this->assertNull($this->uploader->validate($this->file(base64_decode(self::PNG), 'photo.png')));
    }

    public function testRejectsAFileThatOnlyPretendsToBeAnImage(): void
    {
        $error = $this->uploader->validate($this->file('<?php echo "pirate"; ?>', 'photo.jpg'));

        $this->assertSame('Formats acceptés : JPG, PNG, WebP ou GIF.', $error);
    }

    public function testRejectsAFileTooLargeForPhp(): void
    {
        $path = $this->directory . '/big';
        touch($path);
        $file = new UploadedFile($path, 'big.png', null, \UPLOAD_ERR_INI_SIZE, true);

        $this->assertStringContainsString('trop lourde', $this->uploader->validate($file));
    }

    public function testUploadUsesARandomNameAndTheRealExtension(): void
    {
        // Le nom d'origine (avec un chemin piège) n'est jamais réutilisé
        $path = $this->uploader->upload($this->file(base64_decode(self::PNG), '../../evil.php'));

        $this->assertMatchesRegularExpression('#^/uploads/covers/[a-f0-9]{32}\.png$#', $path);
        $this->assertFileExists($this->directory . '/' . basename($path));
        $this->assertTrue(CoverUploader::isUploaded($path));
    }

    public function testRemoveDeletesAnUploadedCover(): void
    {
        $path = $this->uploader->upload($this->file(base64_decode(self::PNG), 'photo.png'));

        $this->uploader->remove($path);

        $this->assertFileDoesNotExist($this->directory . '/' . basename($path));
    }

    /** @dataProvider notUploadedCovers */
    public function testRemoveIgnoresAnythingThatIsNotAnUploadedCover(?string $cover): void
    {
        file_put_contents($this->directory . '/index.php', 'ne pas supprimer');

        $this->assertFalse(CoverUploader::isUploaded($cover));
        $this->uploader->remove($cover);

        $this->assertFileExists($this->directory . '/index.php');
    }

    public static function notUploadedCovers(): array
    {
        return [
            'URL externe' => ['https://picsum.photos/1920/1080'],
            'remontée de dossier' => ['/uploads/covers/../index.php'],
            'nom non aléatoire' => ['/uploads/covers/index.php'],
            'vide' => [null],
        ];
    }
}
