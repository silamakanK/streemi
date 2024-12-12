<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Comment;
use App\Entity\Episode;
use App\Entity\Language;
use App\Entity\Media;
use App\Entity\Movie;
use App\Entity\Playlist;
use App\Entity\PlaylistMedia;
use App\Entity\PlaylistSubscription;
use App\Entity\Season;
use App\Entity\Serie;
use App\Entity\Subscription;
use App\Entity\SubscriptionHistory;
use App\Entity\User;
use App\Enum\CommentStatusEnum;
use App\Enum\UserStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public const MAX_USERS = 10;
    public const MAX_MEDIA = 100;
    public const MAX_SUBSCRIPTIONS = 3;
    public const MAX_SEASONS = 3;
    public const MAX_EPISODES = 10;

    public const PLAYLISTS_PER_USER = 3;
    public const MAX_MEDIA_PER_PLAYLIST = 3;
    public const MAX_LANGUAGE_PER_MEDIA = 3;
    public const MAX_CATEGORY_PER_MEDIA = 3;
    public const MAX_SUBSCRIPTIONS_HISTORY_PER_USER = 3;
    public const MAX_COMMENTS_PER_MEDIA = 10;
    public const MAX_PLAYLIST_SUBSCRIPTION_PER_USERS = 3;

    public function load(ObjectManager $manager): void
    {
        $users = [];
        $medias = [];
        $playlists = [];
        $categories = [];
        $languages = [];
        $subscriptions = [];

        $this->createUsers($manager, $users);
        $this->createPlaylists($manager, $users, $playlists);
        $this->createSubscriptions($manager, $users, $subscriptions);
        $this->createCategories($manager, $categories);
        $this->createLanguages($manager, $languages);
        $this->createMedia($manager, $medias);
        $this->createComments($manager, $medias, $users);

        $this->linkMediaToPlaylists($medias, $playlists, $manager);
        $this->linkSubscriptionToUsers($users, $subscriptions, $manager);
        $this->linkMediaToCategories($medias, $categories);
        $this->linkMediaToLanguages($medias, $languages);

        $this->addUserPlaylistSubscriptions($manager, $users, $playlists);

        $manager->flush();
    }

    protected function createSubscriptions(ObjectManager $manager, array &$users, array &$subscriptions): void
    {
        $array = [
            ['name' => 'Abonnement 1 mois - HD', 'duration' => 1, 'price' => 3],
            ['name' => 'Abonnement 3 mois - HD', 'duration' => 3, 'price' => 8],
            ['name' => 'Abonnement 6 mois - HD', 'duration' => 6, 'price' => 15],
            ['name' => 'Abonnement 1 an - HD', 'duration' => 12, 'price' => 25],
            ['name' => 'Abonnement 1 mois - 4K HDR', 'duration' => 1, 'price' => 6],
            ['name' => 'Abonnement 3 mois - 4K HDR', 'duration' => 3, 'price' => 15],
            ['name' => 'Abonnement 6 mois - 4K HDR', 'duration' => 6, 'price' => 30],
            ['name' => 'Abonnement 1 an - 4K HDR', 'duration' => 12, 'price' => 50],

        ];

        foreach ($array as $element) {
            $abonnement = new Subscription();
            $abonnement->setDuration($element['duration']);
            $abonnement->setName($element['name']);
            $abonnement->setPrice($element['price']);
            $abonnement->setDescription('Description de l\'abonnement');
            $manager->persist($abonnement);
            $subscriptions[] = $abonnement;

            for ($i = 0; $i < random_int(1, self::MAX_SUBSCRIPTIONS); $i++) {
                $randomUser = $users[array_rand($users)];
                $randomUser->setCurrentSubscription(currentSubscription: $abonnement);
            }
        }
    }

    protected function createMedia(ObjectManager $manager, array &$medias): void
    {
        for ($j = 0; $j < self::MAX_MEDIA; $j++) {
            $media = random_int(min: 0, max: 1) === 0 ? new Movie() : new Serie();
            $title = $media instanceof Movie ? 'Film' : 'Série';

            $media->setTitle(title: "{$title} n°{$j}");
            $media->setLongDescription(longDescription: "Longue description {$j}");
            $media->setShortDescription(shortDescription: "Short description {$j}");
            $media->setCoverImage(coverImage: "https://picsum.photos/1920/1080?random={$j}");
            $media->setReleaseDate(releaseDate: new \DateTime(datetime: "+7 days"));
            $manager->persist(object: $media);
            $medias[] = $media;

            $this->addCastingAndStaff($media);

            if ($media instanceof Serie) {
                $this->createSeasons($manager, $media);
            }

            if ($media instanceof Movie) {
//                $media->setDuration(duration: random_int(60, 180));
            }
        }
    }

    protected function createUsers(ObjectManager $manager, array &$users): void
    {
        for ($i = 0; $i < self::MAX_USERS; $i++) {
            $user = new User();
            $user->setEmail(email: "test_{$i}@example.com");
            $user->setUsername(username: "test_{$i}");
            $user->setPassword(password: 'motdepasse');
            $user->setAccountStatus(UserStatusEnum::ACTIVE);
            $users[] = $user;

            $manager->persist(object: $user);
        }

        $admin = new User();
        $admin->setEmail(email: "admin@example.com");
        $admin->setUsername(username: "admin");
        $admin->setPassword('motdepasse');
        $admin->setAccountStatus(UserStatusEnum::ACTIVE);
        $admin->addRole('ROLE_ADMIN');
        $manager->persist($admin);

        $normal = new User();
        $normal->setEmail(email: "demo@example.com");
        $normal->setUsername(username: "demo");
        $normal->setPassword('motdepasse');
        $normal->setAccountStatus(UserStatusEnum::ACTIVE);
        $normal->addRole('ROLE_USER');
        $manager->persist($normal);
    }

    public function createPlaylists(ObjectManager $manager, array &$users, array &$playlists): void
    {
        foreach ($users as $user) {
            for ($k = 0; $k < self::PLAYLISTS_PER_USER; $k++) {
                $playlist = new Playlist();
                $playlist->setName(name: "Ma playlist {$k}");
                $playlist->setCreatedAt(createdAt: new \DateTimeImmutable());
                $playlist->setUpdatedAt(updatedAt: new \DateTimeImmutable());
                $playlist->setCreator(creator: $user);
                $playlists[] = $playlist;

                $manager->persist(object: $playlist);
            }
        }
    }

    protected function createCategories(ObjectManager $manager, array &$categories): void
    {
        $array = [
            ['nom' => 'Action', 'label' => 'Action'],
            ['nom' => 'Comédie', 'label' => 'Comédie'],
            ['nom' => 'Drame', 'label' => 'Drame'],
            ['nom' => 'Horreur', 'label' => 'Horreur'],
            ['nom' => 'Science-fiction', 'label' => 'Science-fiction'],
            ['nom' => 'Thriller', 'label' => 'Thriller'],
        ];

        foreach ($array as $element) {
            $category = new Category();
            $category->setNom($element['nom']);
            $category->setLabel($element['label']);
            $manager->persist($category);
            $categories[] = $category;
        }
    }

    protected function createLanguages(ObjectManager $manager, array &$languages): void
    {
        $array = [
            ['code' => 'fr', 'nom' => 'Français'],
            ['code' => 'en', 'nom' => 'Anglais'],
            ['code' => 'es', 'nom' => 'Espagnol'],
            ['code' => 'de', 'nom' => 'Allemand'],
            ['code' => 'it', 'nom' => 'Italien'],
        ];

        foreach ($array as $element) {
            $language = new Language();
            $language->setCode($element['code']);
            $language->setNom($element['nom']);
            $manager->persist($language);
            $languages[] = $language;
        }
    }

    protected function createSeasons(ObjectManager $manager, Serie $media): void
    {
        for ($i = 0; $i < random_int(1, self::MAX_SEASONS); $i++) {
            $season = new Season();
            $season->setNumber('Saison ' . ($i + 1));
            $season->setSerie($media);

            $manager->persist($season);
            $this->createEpisodes($season, $manager);
        }
    }

    protected function createEpisodes(Season $season, ObjectManager $manager): void
    {
        for ($i = 0; $i < random_int(1, self::MAX_EPISODES); $i++) {
            $episode = new Episode();
            $episode->setTitle('Episode ' . ($i + 1));
            $episode->setDuration(random_int(10, 60));
            $episode->setReleasedAt(new \DateTimeImmutable());
            $episode->setSeason($season);

            $manager->persist($episode);
        }
    }

    protected function createComments(ObjectManager $manager, array $medias, array $users): void
    {
        /** @var Media $media */
        foreach ($medias as $media) {
            for ($i = 0; $i < random_int(1, self::MAX_COMMENTS_PER_MEDIA); $i++) {
                $comment = new Comment();
                $comment->setPublisher($users[array_rand($users)]);
                $comment->setContent("Commentaire {$i}");
//                $comment->setCreatedAt(new \DateTimeImmutable());
                $comment->setStatus(random_int(0, 1) === 1 ? CommentStatusEnum::VALID : CommentStatusEnum::WAITING);
                $comment->setMedia($media);

//                $shouldHaveParent = random_int(0, 5) < 2;
//                if ($shouldHaveParent) {
//                    $parentComment = new Comment();
//                    $parentComment->setPublisher($users[array_rand($users)]);
//                    $parentComment->setContent("Commentaire parent");
//                    $parentComment->setCreatedAt(new \DateTimeImmutable());
//                    $parentComment->setStatus(random_int(0, 1) === 1 ? CommentStatusEnum::VALIDATED : CommentStatusEnum::WAITING);
//                    $parentComment->setMedia($media);
//                    $comment->setParentComment($parentComment);
//                    $manager->persist($parentComment);
//                }

                $manager->persist($comment);
            }
        }
    }

    // link methods

    protected function linkMediaToCategories(array $medias, array $categories): void
    {
        /** @var Media $media */
        foreach ($medias as $media) {
            for ($i = 0; $i < random_int(1, self::MAX_CATEGORY_PER_MEDIA); $i++) {
                $media->addCategory($categories[array_rand($categories)]);
            }
        }
    }

    protected function linkMediaToLanguages(array $medias, array $languages): void
    {
        /** @var Media $media */
        foreach ($medias as $media) {
            for ($i = 0; $i < random_int(1, self::MAX_LANGUAGE_PER_MEDIA); $i++) {
                $media->addLanguage($languages[array_rand($languages)]);
            }
        }
    }

    protected function linkMediaToPlaylists(array $medias, array $playlists, ObjectManager $manager): void
    {
        /** @var Media $media */
        foreach ($medias as $media) {
            for ($i = 0; $i < random_int(1, self::MAX_MEDIA_PER_PLAYLIST); $i++) {
                $playlistMedia = new PlaylistMedia();
                $playlistMedia->setMedia(media: $media);
                $playlistMedia->setAddedAt(addedAt: new \DateTimeImmutable());
                $playlistMedia->setPlaylist(playlist: $playlists[array_rand($playlists)]);
                $manager->persist(object: $playlistMedia);
            }
        }
    }

    protected function linkSubscriptionToUsers(array $users, array $subscriptions, ObjectManager $manager): void
    {
        foreach ($users as $user) {
            $sub = $subscriptions[array_rand($subscriptions)];

            for ($i = 0; $i < random_int(1, self::MAX_SUBSCRIPTIONS_HISTORY_PER_USER); $i++) {
                $history = new SubscriptionHistory();
                $history->setSubscriber($user);
                $history->setSubscription($sub);
                $history->setStartAt(new \DateTimeImmutable());
                $history->setEndAt(new \DateTimeImmutable());
            }

            $manager->persist($history);
        }
    }

    protected function addCastingAndStaff(Media $media)
    {
        $staffData = [
            ['name' => 'John Doe', 'role' => 'Réalisateur', 'image' => 'https://i.pravatar.cc/500/150?u=John+Doe'],
            ['name' => 'Jane Doe', 'role' => 'Scénariste', 'image' => 'https://i.pravatar.cc/500/150?u=Jane+Doe'],
            ['name' => 'Foo Bar', 'role' => 'Compositeur', 'image' => 'https://i.pravatar.cc/500/150?u=Foo+Bar'],
            ['name' => 'Baz Qux', 'role' => 'Producteur', 'image' => 'https://i.pravatar.cc/500/150?u=Baz+Qux'],
            ['name' => 'Alice Bob', 'role' => 'Directeur de la photographie', 'image' => 'https://i.pravatar.cc/500/150?u=Alice+Bob'],
            ['name' => 'Charlie Delta', 'role' => 'Monteur', 'image' => 'https://i.pravatar.cc/500/150?u=Charlie+Delta'],
            ['name' => 'Eve Fox', 'role' => 'Costumier', 'image' => 'https://i.pravatar.cc/500/150?u=Eve+Fox'],
            ['name' => 'Grace Hope', 'role' => 'Maquilleur', 'image' => 'https://i.pravatar.cc/500/150?u=Grace+Hope'],
            ['name' => 'Ivy Jack', 'role' => 'Cascades', 'image' => 'https://i.pravatar.cc/500/150?u=Ivy+Jack'],
        ];

        $castingData = [
            ['name' => 'John Doe', 'role' => 'Réalisateur', 'image' => 'https://i.pravatar.cc/150?u=John+Doe'],
            ['name' => 'Jane Doe', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Jane+Doe'],
            ['name' => 'Foo Bar', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Foo+Bar'],
            ['name' => 'Baz Qux', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Baz+Qux'],
            ['name' => 'Alice Bob', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Alice+Bob'],
            ['name' => 'Charlie Delta', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Charlie+Delta'],
            ['name' => 'Eve Fox', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Eve+Fox'],
            ['name' => 'Grace Hope', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Grace+Hope'],
            ['name' => 'Ivy Jack', 'role' => 'Acteur', 'image' => 'https://i.pravatar.cc/150?u=Ivy+Jack'],
        ];

        $staff = [];
        for ($i = 0; $i < random_int(2, 5); $i++) {
            $staff[] = $staffData[array_rand($staffData)];
        }

        $media->setStaff($staff);

        $casting = [];
        for ($i = 0; $i < random_int(3, 5); $i++) {
            $casting[] = $castingData[array_rand($castingData)];
        }

        $media->setCasting($casting);
    }

    protected function addUserPlaylistSubscriptions(ObjectManager $manager, array $users, array $playlists): void
    {
        /** @var User $user */
        foreach ($users as $user) {
            for ($i = 0; $i < random_int(0, self::MAX_PLAYLIST_SUBSCRIPTION_PER_USERS); $i++) {
                $subscription = new PlaylistSubscription();
                $subscription->setSubscriber($user);
                $subscription->setPlaylist($playlists[array_rand($playlists)]);
                $subscription->setSubscribedAt(new \DateTimeImmutable());
                $manager->persist($subscription);
            }
        }
    }
}