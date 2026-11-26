<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PromoteAdminCommandTest extends KernelTestCase
{
    private function promote(string $email): CommandTester
    {
        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:user:promote'));
        $tester->execute(['email' => $email]);

        return $tester;
    }

    public function testPromotesAnExistingUser(): void
    {
        $tester = $this->promote('demo@example.com');

        $tester->assertCommandIsSuccessful();
        $user = static::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email' => 'demo@example.com']);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testFailsForAnUnknownEmail(): void
    {
        $this->assertSame(1, $this->promote('inconnu@example.com')->getStatusCode());
    }
}
