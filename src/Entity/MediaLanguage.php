<?php

namespace App\Entity;

use App\Repository\MediaLanguageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaLanguageRepository::class)]
class MediaLanguage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Media $media_id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMediaId(): ?Media
    {
        return $this->media_id;
    }

    public function setMediaId(Media $media_id): static
    {
        $this->media_id = $media_id;

        return $this;
    }

    public function getLanguageId(): ?Language
    {
        return $this->language_id;
    }

    public function setLanguageId(Language $language_id): static
    {
        $this->language_id = $language_id;

        return $this;
    }
}
