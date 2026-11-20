<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : un navigateur de test et des raccourcis vers les données des fixtures.
 * Chaque test tourne dans une transaction annulée à la fin (dama/doctrine-test-bundle).
 */
abstract class AppWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function user(string $email): User
    {
        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    protected function loginAs(string $email): User
    {
        $user = $this->user($email);
        $this->client->loginUser($user);

        return $user;
    }

    protected function loginAsAdmin(): User
    {
        return $this->loginAs('admin@example.com');
    }

    protected function media(string $title): Media
    {
        return $this->em()->getRepository(Media::class)->findOneBy(['title' => $title]);
    }

    protected function category(string $nom): Category
    {
        return $this->em()->getRepository(Category::class)->findOneBy(['nom' => $nom]);
    }

    /** Texte des messages flash affichés sur la page courante */
    protected function flashes(): string
    {
        return implode(' | ', $this->client->getCrawler()->filter('main > div.bg-green-500, main > div.bg-red-600')->each(fn($node) => trim($node->text())));
    }

    /** Recharge une entité depuis la base (après une requête qui l'a modifiée) */
    protected function reload(object $entity): ?object
    {
        $this->em()->clear();

        return $this->em()->find($entity::class, $entity->getId());
    }
}
