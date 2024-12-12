<?php

namespace App\Entity;

use App\Repository\MovieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
class Movie extends Media
{
    public function getMediaType(): string
    {
        return 'Movie';
    }
}
