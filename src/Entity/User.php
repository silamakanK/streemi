<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'no')]
    private Collection $current_subscription_id;

    public function __construct()
    {
        $this->current_subscription_id = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getCurrentSubscriptionId(): Collection
    {
        return $this->current_subscription_id;
    }

    public function addCurrentSubscriptionId(Subscription $currentSubscriptionId): static
    {
        if (!$this->current_subscription_id->contains($currentSubscriptionId)) {
            $this->current_subscription_id->add($currentSubscriptionId);
            $currentSubscriptionId->setNo($this);
        }

        return $this;
    }

    public function removeCurrentSubscriptionId(Subscription $currentSubscriptionId): static
    {
        if ($this->current_subscription_id->removeElement($currentSubscriptionId)) {
            // set the owning side to null (unless already changed)
            if ($currentSubscriptionId->getNo() === $this) {
                $currentSubscriptionId->setNo(null);
            }
        }

        return $this;
    }
}
