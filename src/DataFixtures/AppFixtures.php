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
use App\Enum\CatalogTypeEnum;
use App\Enum\CommentStatusEnum;
use App\Enum\UserStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;



class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

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

        // « Animation » est réservée aux vrais animés : on ne la distribue pas au hasard
        $genres = array_filter($categories, fn(Category $category) => $category->getNom() !== CatalogTypeEnum::ANIMATION_CATEGORY);
        $this->linkMediaToCategories($medias, $genres);
        $this->linkMediaToLanguages($medias, $languages);

        $this->createAnimes($manager, $medias, $categories, $languages);
        $this->createComments($manager, $medias, $users);

        $this->linkMediaToPlaylists($medias, $playlists, $manager);
        $this->linkSubscriptionToUsers($users, $subscriptions, $manager);

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

    protected function createAnimes(ObjectManager $manager, array &$medias, array $categories, array $languages): void
    {
        $array = [
            // Classiques
            ['title' => 'Akira', 'type' => 'movie', 'date' => '1988-07-16', 'genres' => ['Science-fiction', 'Action'],
             'short' => 'Néo-Tokyo, 2019 : un adolescent développe des pouvoirs psychiques incontrôlables.',
             'long' => 'Trente ans après une explosion qui a rasé Tokyo, la mégapole reconstruite est rongée par les gangs et la contestation. Lorsque Tetsuo, membre d\'une bande de motards, est enlevé par l\'armée et soumis à des expériences secrètes, ses pouvoirs menacent de détruire la ville une seconde fois.'],
            ['title' => 'Mon voisin Totoro', 'type' => 'movie', 'date' => '1988-04-16', 'genres' => ['Comédie'],
             'short' => 'Deux sœurs installées à la campagne découvrent les esprits de la forêt.',
             'long' => 'Satsuki et sa petite sœur Mei emménagent avec leur père dans une vieille maison près d\'une forêt, pour se rapprocher de leur mère hospitalisée. Elles y rencontrent Totoro, un grand esprit gardien aussi discret que bienveillant.'],
            ['title' => 'Dragon Ball Z', 'type' => 'serie', 'date' => '1989-04-26', 'genres' => ['Action'],
             'short' => 'Son Goku découvre ses origines saiyennes et affronte des ennemis venus de l\'espace.',
             'long' => 'Devenu père, Son Goku apprend qu\'il appartient à une race de guerriers extraterrestres. Face à des adversaires toujours plus puissants, lui et ses amis repoussent sans cesse leurs limites pour protéger la Terre.'],
            ['title' => 'Ghost in the Shell', 'type' => 'movie', 'date' => '1995-11-18', 'genres' => ['Science-fiction', 'Thriller'],
             'short' => 'Une cyborg de la police traque un pirate capable de pénétrer les esprits.',
             'long' => 'Dans un futur où les corps sont augmentés et les cerveaux connectés, le Major Motoko Kusanagi enquête sur le Puppet Master, un hacker insaisissable. L\'affaire l\'amène à s\'interroger sur ce qui fait encore d\'elle un être humain.'],
            ['title' => 'Neon Genesis Evangelion', 'type' => 'serie', 'date' => '1995-10-04', 'genres' => ['Science-fiction', 'Drame'],
             'short' => 'Des adolescents pilotent des robots géants contre de mystérieux Anges.',
             'long' => 'Shinji Ikari, 14 ans, est convoqué par son père pour piloter l\'Eva-01 et défendre Tokyo-3 contre les Anges. Derrière les combats, la série explore la solitude, la peur de l\'autre et les secrets de l\'organisation NERV.'],
            ['title' => 'Princesse Mononoké', 'type' => 'movie', 'date' => '1997-07-12', 'genres' => ['Action', 'Drame'],
             'short' => 'Un prince maudit se retrouve au cœur d\'une guerre entre les hommes et la forêt.',
             'long' => 'Frappé par la malédiction d\'un dieu sanglier, le prince Ashitaka part vers l\'ouest chercher un remède. Il découvre une cité de forges qui détruit la forêt, et San, une jeune fille élevée par les loups qui a juré de la défendre.'],
            ['title' => 'Cowboy Bebop', 'type' => 'serie', 'date' => '1998-04-03', 'genres' => ['Science-fiction', 'Action'],
             'short' => 'Un équipage de chasseurs de primes fauchés parcourt le système solaire.',
             'long' => 'En 2071, Spike Spiegel et Jet Black sillonnent l\'espace à bord du Bebop à la recherche de primes. Entre jazz, combats et passé qui refait surface, chaque mission les rapproche de ce qu\'ils fuient.'],
            ['title' => 'One Piece', 'type' => 'serie', 'date' => '1999-10-20', 'genres' => ['Action', 'Comédie'],
             'short' => 'Luffy et son équipage partent à la recherche du trésor légendaire One Piece.',
             'long' => 'Monkey D. Luffy, un garçon au corps élastique, rêve de devenir le roi des pirates. Île après île, il réunit un équipage hors du commun et défie la Marine comme les pirates les plus redoutés.'],
            ['title' => 'Le Voyage de Chihiro', 'type' => 'movie', 'date' => '2001-07-20', 'genres' => ['Drame'],
             'short' => 'Une fillette égarée dans le monde des esprits doit sauver ses parents.',
             'long' => 'En chemin vers leur nouvelle maison, Chihiro et ses parents pénètrent dans une ville abandonnée qui, la nuit venue, accueille les esprits. Pour libérer ses parents transformés en cochons, elle doit travailler dans les bains de la sorcière Yubaba.'],
            ['title' => 'Naruto', 'type' => 'serie', 'date' => '2002-10-03', 'genres' => ['Action'],
             'short' => 'Un jeune ninja rejeté par son village rêve d\'en devenir le chef.',
             'long' => 'Naruto Uzumaki porte en lui le démon renard qui a ravagé le village de Konoha. Turbulent et isolé, il veut devenir Hokage pour être enfin reconnu, et se forge des liens solides avec ses coéquipiers.'],
            ['title' => 'Death Note', 'type' => 'serie', 'date' => '2006-10-04', 'genres' => ['Thriller'],
             'short' => 'Un lycéen trouve un carnet qui tue quiconque y voit son nom inscrit.',
             'long' => 'Light Yagami décide d\'utiliser le Death Note pour débarrasser le monde des criminels. Le mystérieux détective L se lance à sa poursuite, dans un duel psychologique où chaque erreur peut être fatale.'],
            ['title' => 'Fullmetal Alchemist: Brotherhood', 'type' => 'serie', 'date' => '2009-04-05', 'genres' => ['Action', 'Drame'],
             'short' => 'Deux frères alchimistes cherchent la pierre philosophale pour réparer leurs corps.',
             'long' => 'Après une tentative ratée de ressusciter leur mère, Edward a perdu un bras et une jambe, et Alphonse son corps entier. Leur quête de la pierre philosophale révèle une conspiration qui menace tout le pays.'],
            // Récents
            ['title' => 'L\'Attaque des Titans', 'type' => 'serie', 'date' => '2013-04-07', 'genres' => ['Action', 'Horreur'],
             'short' => 'L\'humanité, retranchée derrière des murs, affronte des géants dévoreurs d\'hommes.',
             'long' => 'Depuis un siècle, les derniers humains vivent à l\'abri de trois murs immenses. Le jour où un Titan colossal les franchit, Eren Jäger jure d\'exterminer les Titans, avant de découvrir la terrible vérité sur leur origine.'],
            ['title' => 'Your Name', 'type' => 'movie', 'date' => '2016-08-26', 'genres' => ['Drame'],
             'short' => 'Une lycéenne de la campagne et un lycéen de Tokyo échangent leurs corps en rêve.',
             'long' => 'Mitsuha et Taki se réveillent régulièrement dans le corps de l\'autre. Ils apprennent à communiquer par messages, jusqu\'au jour où les échanges cessent et où Taki part à la recherche d\'une fille qu\'il n\'a jamais rencontrée.'],
            ['title' => 'Demon Slayer', 'type' => 'serie', 'date' => '2019-04-06', 'genres' => ['Action', 'Horreur'],
             'short' => 'Un jeune homme devient pourfendeur de démons pour sauver sa sœur.',
             'long' => 'Sa famille massacrée et sa sœur Nezuko transformée en démon, Tanjirō Kamado rejoint l\'ordre des pourfendeurs. Il cherche un moyen de rendre Nezuko humaine tout en traquant le démon responsable de la tragédie.'],
            ['title' => 'Jujutsu Kaisen', 'type' => 'serie', 'date' => '2020-10-03', 'genres' => ['Action', 'Horreur'],
             'short' => 'Un lycéen avale un doigt maudit et devient l\'hôte d\'un puissant fléau.',
             'long' => 'Pour sauver ses amis, Yuji Itadori ingère une relique de Sukuna, le roi des fléaux. Condamné à mort mais sursitaire, il intègre une école d\'exorcistes chargée de combattre les malédictions.'],
            ['title' => 'Spy x Family', 'type' => 'serie', 'date' => '2022-04-09', 'genres' => ['Comédie', 'Action'],
             'short' => 'Un espion, une tueuse et une télépathe forment une fausse famille.',
             'long' => 'Pour sa mission, l\'espion Twilight doit fonder une famille. Il adopte Anya et épouse Yor, sans savoir que l\'une lit dans les pensées et que l\'autre est une redoutable tueuse à gages.'],
            ['title' => 'Chainsaw Man', 'type' => 'serie', 'date' => '2022-10-12', 'genres' => ['Action', 'Horreur'],
             'short' => 'Un chasseur de démons criblé de dettes fusionne avec son chien-tronçonneuse.',
             'long' => 'Denji vit dans la misère avec Pochita, un petit démon tronçonneuse. Trahi et tué, il revient à la vie en fusionnant avec lui et se retrouve recruté par une division officielle de chasseurs de démons.'],
            ['title' => 'Suzume', 'type' => 'movie', 'date' => '2022-11-11', 'genres' => ['Drame'],
             'short' => 'Une lycéenne parcourt le Japon pour refermer des portes qui libèrent des catastrophes.',
             'long' => 'Suzume ouvre par erreur une porte mystérieuse au milieu des ruines. Avec Sōta, transformé en chaise, elle traverse le pays pour refermer ces portes avant qu\'elles ne déclenchent de nouveaux séismes.'],
            ['title' => 'The First Slam Dunk', 'type' => 'movie', 'date' => '2022-12-03', 'genres' => ['Drame'],
             'short' => 'Un match de basket décisif, vu à travers les souvenirs du meneur Ryota.',
             'long' => 'Le lycée Shohoku affronte Sannoh, l\'équipe championne. Au fil du match, Ryota Miyagi revit son enfance, la perte de son frère et tout ce qui l\'a mené sur ce terrain.'],
            ['title' => 'Le Garçon et le Héron', 'type' => 'movie', 'date' => '2023-07-14', 'genres' => ['Drame'],
             'short' => 'Endeuillé, un garçon suit un héron parlant dans un monde fantastique.',
             'long' => 'Pendant la guerre, Mahito perd sa mère et part vivre à la campagne. Un héron étrange l\'attire dans une tour abandonnée, porte d\'entrée d\'un monde où vivants et morts se croisent.'],
            ['title' => 'Frieren', 'type' => 'serie', 'date' => '2023-09-29', 'genres' => ['Drame'],
             'short' => 'Une magicienne elfe repart sur les routes après la mort de ses compagnons.',
             'long' => 'Après avoir vaincu le Roi Démon, Frieren voit ses compagnons humains vieillir puis disparaître. Pour comprendre ce qu\'elle a manqué, elle entreprend un nouveau voyage sur les traces de leur aventure.'],
            ['title' => 'Solo Leveling', 'type' => 'serie', 'date' => '2024-01-07', 'genres' => ['Action'],
             'short' => 'Le chasseur le plus faible du monde obtient le pouvoir de progresser sans limite.',
             'long' => 'Sung Jin-Woo, chasseur de rang E, survit de justesse à un donjon mortel. Il se réveille avec un mystérieux « système » qui lui permet de devenir plus fort à chaque combat.'],
            ['title' => 'Dandadan', 'type' => 'serie', 'date' => '2024-10-04', 'genres' => ['Comédie', 'Science-fiction'],
             'short' => 'Une lycéenne qui croit aux fantômes et un garçon fan d\'aliens découvrent que tout existe.',
             'long' => 'Momo croit aux esprits, Okarun aux extraterrestres. Pour se prouver mutuellement qu\'ils ont raison, ils se lancent un défi qui les plonge dans un monde peuplé de yōkai et d\'aliens.'],
        ];

        $categoriesByName = [];
        foreach ($categories as $category) {
            $categoriesByName[$category->getNom()] = $category;
        }
        $languagesByCode = [];
        foreach ($languages as $language) {
            $languagesByCode[$language->getCode()] = $language;
        }

        foreach ($array as $element) {
            $media = $element['type'] === 'movie' ? new Movie() : new Serie();
            $media->setTitle($element['title']);
            $media->setShortDescription($element['short']);
            $media->setLongDescription($element['long']);
            $media->setCoverImage('https://picsum.photos/seed/' . md5($element['title']) . '/1920/1080');
            $media->setReleaseDate(new \DateTime($element['date']));

            $media->addCategory($categoriesByName[CatalogTypeEnum::ANIMATION_CATEGORY]);
            foreach ($element['genres'] as $genre) {
                $media->addCategory($categoriesByName[$genre]);
            }
            $media->addLanguage($languagesByCode['ja']);
            $media->addLanguage($languagesByCode['fr']);

            $this->addCastingAndStaff($media);
            $manager->persist($media);
            $medias[] = $media;

            if ($media instanceof Serie) {
                $this->createSeasons($manager, $media);
            }
        }
    }

    protected function createUsers(ObjectManager $manager, array &$users): void
    {
        for ($i = 0; $i < self::MAX_USERS; $i++) {
            $user = new User();
            $user->setEmail("test_{$i}@example.com");
            $user->setUsername("test_{$i}");
            $user->setPassword($this->hasher->hashPassword($user, 'motdepasse'));
            $user->setAccountStatus(UserStatusEnum::ACTIVE);
            $users[] = $user;
            $manager->persist($user);
        }

        $admin = new User();
        $admin->setEmail("admin@example.com");
        $admin->setUsername("admin");
        $admin->setPassword($this->hasher->hashPassword($admin, 'motdepasse'));
        $admin->setAccountStatus(UserStatusEnum::ACTIVE);
        $admin->addRole('ROLE_ADMIN');
        $manager->persist($admin);

        $normal = new User();
        $normal->setEmail("demo@example.com");
        $normal->setUsername("demo");
        $normal->setPassword($this->hasher->hashPassword($normal, 'motdepasse'));
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
            ['nom' => 'Animation', 'label' => 'Animation'],
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
            ['code' => 'ja', 'nom' => 'Japonais'],
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
                $manager->persist($history);
            }
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