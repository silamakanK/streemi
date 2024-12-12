<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column]
    private ?int $duration_in_months = null;

    #[ORM\ManyToOne(inversedBy: 'current_subscription_id')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $no = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDurationInMonths(): ?int
    {
        return $this->duration_in_months;
    }

    public function setDurationInMonths(int $duration_in_months): static
    {
        $this->duration_in_months = $duration_in_months;

        return $this;
    }

    public function getNo(): ?User
    {
        return $this->no;
    }

    public function setNo(?User $no): static
    {
        $this->no = $no;

        return $this;
    }
}
