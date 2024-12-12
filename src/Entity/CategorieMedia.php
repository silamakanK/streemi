<?php

namespace App\Entity;

use App\Repository\CategorieMediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieMediaRepository::class)]
class CategorieMedia
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
    private ?Categorie $categorie_id = null;

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

    public function getCategorieId(): ?Categorie
    {
        return $this->categorie_id;
    }

    public function setCategorieId(Categorie $categorie_id): static
    {
        $this->categorie_id = $categorie_id;

        return $this;
    }
}
