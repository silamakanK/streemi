<?php

namespace App\Entity;

use App\Enum\CommentStatusEnum;
use App\Repository\MediaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\InheritanceType;
use \App\Enum\MediaTypeEnum;

#[InheritanceType('JOINED')]
#[DiscriminatorColumn(name: 'discr', type: 'string')]
#[DiscriminatorMap(['serie' => Serie::class, 'movie' => Movie::class])]
#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'media')]
    private Collection $comments;

    /**
     * @var Collection<int, WatchHistory>
     */
    #[ORM\OneToMany(targetEntity: WatchHistory::class, mappedBy: 'media')]
    private Collection $watchHistories;

    /**
     * @var Collection<int, PlaylistMedia>
     */
    // #[ORM\OneToMany(targetEntity: PlaylistMedia::class, mappedBy: 'media')]
    // private Collection $playlistMedia;

    /**
     * @var Collection<int, Category>
     */
    // #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'medias')]
    // private Collection $categories;

    /**
     * @var Collection<int, Language>
     */
    #[ORM\ManyToMany(targetEntity: Language::class, inversedBy: 'medias')]
    private Collection $languages;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $longDescription = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $releaseDate = null;

    #[ORM\Column(length: 255)]
    private ?string $coverImage = null;

    #[ORM\Column]
    private array $staff = [];

    #[ORM\Column]
    private array $casting = [];

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->watchHistories = new ArrayCollection();
        // $this->playlistMedia = new ArrayCollection();
        // $this->categories = new ArrayCollection();
        $this->languages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function getValidatedComments(): Collection
    {
        return $this->comments->filter(static function(Comment $comment) {
            return $comment->getStatus() === CommentStatusEnum::VALID;
        });
    }

    // public function addComment(Comment $comment): static
    // {
    //     if (!$this->comments->contains($comment)) {
    //         $this->comments->add($comment);
    //         // $comment->setMedia($this);
    //     }

    //     return $this;
    // }

    // public function removeComment(Comment $comment): static
    // {
    //     if ($this->comments->removeElement($comment)) {
    //         // set the owning side to null (unless already changed)
    //         if ($comment->getMedia() === $this) {
    //             $comment->setMedia(null);
    //         }
    //     }

    //     return $this;
    // }

    // /**
    //  * @return Collection<int, WatchHistory>
    //  */
    // public function getWatchHistories(): Collection
    // {
    //     return $this->watchHistories;
    // }

    // public function addWatchHistory(WatchHistory $watchHistory): static
    // {
    //     if (!$this->watchHistories->contains($watchHistory)) {
    //         $this->watchHistories->add($watchHistory);
    //         $watchHistory->setMedia($this);
    //     }

    //     return $this;
    // }

    // public function removeWatchHistory(WatchHistory $watchHistory): static
    // {
    //     if ($this->watchHistories->removeElement($watchHistory)) {
    //         // set the owning side to null (unless already changed)
    //         if ($watchHistory->getMedia() === $this) {
    //             $watchHistory->setMedia(null);
    //         }
    //     }

    //     return $this;
    // }

    // /**
    //  * @return Collection<int, PlaylistMedia>
    //  */
    // public function getPlaylistMedia(): Collection
    // {
    //     return $this->playlistMedia;
    // }

    // public function addPlaylistMedium(PlaylistMedia $playlistMedium): static
    // {
    //     if (!$this->playlistMedia->contains($playlistMedium)) {
    //         $this->playlistMedia->add($playlistMedium);
    //         $playlistMedium->setMedia($this);
    //     }

    //     return $this;
    // }

    // public function removePlaylistMedium(PlaylistMedia $playlistMedium): static
    // {
    //     if ($this->playlistMedia->removeElement($playlistMedium)) {
    //         // set the owning side to null (unless already changed)
    //         if ($playlistMedium->getMedia() === $this) {
    //             $playlistMedium->setMedia(null);
    //         }
    //     }

    //     return $this;
    // }

    // /**
    //  * @return Collection<int, Category>
    //  */
    // public function getCategories(): Collection
    // {
    //     return $this->categories;
    // }

    // public function addCategory(Category $category): static
    // {
    //     if (!$this->categories->contains($category)) {
    //         $this->categories->add($category);
    //     }

    //     return $this;
    // }

    // public function removeCategory(Category $category): static
    // {
    //     $this->categories->removeElement($category);

    //     return $this;
    // }

    /**
     * @return Collection<int, Language>
     */
    public function getLanguages(): Collection
    {
        return $this->languages;
    }

    public function addLanguage(Language $language): static
    {
        if (!$this->languages->contains($language)) {
            $this->languages->add($language);
        }

        return $this;
    }

    public function removeLanguage(Language $language): static
    {
        $this->languages->removeElement($language);

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getLongDescription(): ?string
    {
        return $this->longDescription;
    }

    public function setLongDescription(string $longDescription): static
    {
        $this->longDescription = $longDescription;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(\DateTimeInterface $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getStaff(): array
    {
        return $this->staff;
    }

    public function setStaff(array $staff): static
    {
        $this->staff = $staff;

        return $this;
    }

    public function getCasting(): array
    {
        return $this->casting;
    }

    public function setCasting(array $casting): static
    {
        $this->casting = $casting;

        return $this;
    }

    public function getMediaType(): string
    {
        return 'media';
    }
}

/*
namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $short_description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $release_date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cover_image = null;

    #[ORM\Column(nullable: true)]
    private ?array $staff = null;

    #[ORM\Column(nullable: true)]
    private ?array $caste = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->short_description;
    }

    public function setShortDescription(?string $short_description): static
    {
        $this->short_description = $short_description;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->release_date;
    }

    public function setReleaseDate(\DateTimeInterface $release_date): static
    {
        $this->release_date = $release_date;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->cover_image;
    }

    public function setCoverImage(?string $cover_image): static
    {
        $this->cover_image = $cover_image;

        return $this;
    }

    public function getStaff(): ?array
    {
        return $this->staff;
    }

    public function setStaff(?array $staff): static
    {
        $this->staff = $staff;

        return $this;
    }

    public function getCaste(): ?array
    {
        return $this->caste;
    }

    public function setCaste(?array $caste): static
    {
        $this->caste = $caste;

        return $this;
    }
}
*/