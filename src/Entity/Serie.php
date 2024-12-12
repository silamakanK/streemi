<?php

namespace App\Entity;

use App\Repository\SerieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SerieRepository::class)]
class Serie extends Media
{
    /**
     * @var Collection<int, Season>
     */
    #[ORM\OneToMany(targetEntity: Season::class, mappedBy: 'serie_id')]
    private Collection $seasons;

    public function __construct()
    {
        parent::__construct();
        $this->seasons = new ArrayCollection();
    }

    public function getMediaType(): string
    {
        return 'Serie';
    }

    /**
     * @return Collection<int, Season>
     */
    public function getSeasons(): Collection
    {
        return $this->seasons;
    }

    public function addSeason(Season $season): static
    {
        if (!$this->seasons->contains($season)) {
            $this->seasons->add($season);
            $season->setSerieId($this);
        }

        return $this;
    }

    public function removeSeason(Season $season): static
    {
        if ($this->seasons->removeElement($season)) {
            // set the owning side to null (unless already changed)
            if ($season->getSerieId() === $this) {
                $season->setSerieId(null);
            }
        }

        return $this;
    }
}
