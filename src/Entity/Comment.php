<?php

namespace App\Entity;

use App\Enum\UserStatusEnum;
use App\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
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
    private ?Media $media_id = null;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?self $parent_comment_id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(enumType: UserStatusEnum::class)]
    private ?UserStatusEnum $status = null;

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

    public function getMediaId(): ?Media
    {
        return $this->media_id;
    }

    public function setMediaId(Media $media_id): static
    {
        $this->media_id = $media_id;

        return $this;
    }

    public function getParentCommentId(): ?self
    {
        return $this->parent_comment_id;
    }

    public function setParentCommentId(self $parent_comment_id): static
    {
        $this->parent_comment_id = $parent_comment_id;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getStatus(): ?UserStatusEnum
    {
        return $this->status;
    }

    public function setStatus(UserStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }
}
