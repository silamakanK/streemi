<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre les images de couverture envoyées depuis le back-office dans public/uploads/covers.
 */
class CoverUploader
{
    public const PUBLIC_PATH = '/uploads/covers/';
    private const MAX_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/covers')] private string $directory,
        private Filesystem $filesystem,
    ) {}

    /** Renvoie un message d'erreur si le fichier n'est pas une image acceptable, null sinon. */
    public function validate(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return $file->getError() === \UPLOAD_ERR_INI_SIZE || $file->getError() === \UPLOAD_ERR_FORM_SIZE
                ? sprintf('L\'image est trop lourde (%d Mo maximum).', $this->maxSizeInMb())
                : 'L\'envoi de l\'image a échoué, veuillez réessayer.';
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return sprintf('L\'image est trop lourde (%d Mo maximum).', $this->maxSizeInMb());
        }
        // Le type est déterminé à partir du contenu du fichier, pas de son nom
        if (!isset(self::ALLOWED_TYPES[$file->getMimeType()])) {
            return 'Formats acceptés : JPG, PNG, WebP ou GIF.';
        }

        return null;
    }

    /** Déplace le fichier (déjà validé) et renvoie son chemin public, ex. /uploads/covers/3f9a….jpg */
    public function upload(UploadedFile $file): string
    {
        // Nom aléatoire : on n'utilise jamais le nom envoyé par l'utilisateur
        $name = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_TYPES[$file->getMimeType()];
        $file->move($this->directory, $name);

        return self::PUBLIC_PATH . $name;
    }

    /** Supprime une couverture envoyée sur le site. Les URL externes sont ignorées. */
    public function remove(?string $coverImage): void
    {
        if (self::isUploaded($coverImage)) {
            $this->filesystem->remove($this->directory . '/' . basename($coverImage));
        }
    }

    public static function isUploaded(?string $coverImage): bool
    {
        return $coverImage !== null && preg_match('#^' . preg_quote(self::PUBLIC_PATH, '#') . '[a-f0-9]{32}\.(jpg|png|webp|gif)$#', $coverImage) === 1;
    }

    public function maxSizeInMb(): int
    {
        // La limite réelle est la plus petite entre la nôtre et celle de PHP (upload_max_filesize)
        return (int) floor(min(self::MAX_SIZE, UploadedFile::getMaxFilesize()) / 1024 / 1024);
    }
}
