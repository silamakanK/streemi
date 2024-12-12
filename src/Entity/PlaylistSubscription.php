<?php

namespace App\Entity;

use App\Repository\PlaylistSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaylistSubscriptionRepository::class)]
class PlaylistSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user_id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Playlist $playlist_id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $subscribed_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?User
    {
        return $this->user_id;
    }

    public function setUserId(User $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getPlaylistId(): ?Playlist
    {
        return $this->playlist_id;
    }

    public function setPlaylistId(Playlist $playlist_id): static
    {
        $this->playlist_id = $playlist_id;

        return $this;
    }

    public function getSubscribedAt(): ?\DateTimeInterface
    {
        return $this->subscribed_at;
    }

    public function setSubscribedAt(\DateTimeInterface $subscribed_at): static
    {
        $this->subscribed_at = $subscribed_at;

        return $this;
    }
}
