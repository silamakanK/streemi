<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;

class AuthTest extends AppWebTestCase
{
    public function testLoginWithEmailAndPassword(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'demo@example.com', '_password' => 'motdepasse']);

        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertSelectorExists('a[href="/logout"]');
    }

    public function testLoginWithWrongPasswordShowsAnError(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'demo@example.com', '_password' => 'mauvais']);
        $this->client->followRedirect();

        $this->assertSelectorExists('.bg-red-600');
        $this->assertSelectorNotExists('a[href="/logout"]');
    }

    public function testRegisterCreatesAnActiveAccount(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm("S'inscrire", [
            'username' => 'nouveau',
            'email' => 'nouveau@example.com',
            'password' => 'secret123',
            'password_confirm' => 'secret123',
        ]);

        $this->assertResponseRedirects('/login');
        $user = $this->user('nouveau@example.com');
        $this->assertSame('nouveau', $user->getUsername());
        $this->assertNotSame('secret123', $user->getPassword(), 'Le mot de passe doit être haché');
    }

    public function testRegisterRefusesAnEmailAlreadyUsed(): void
    {
        $this->client->request('GET', '/register');
        $this->client->submitForm("S'inscrire", [
            'username' => 'doublon',
            'email' => 'demo@example.com',
            'password' => 'secret123',
            'password_confirm' => 'secret123',
        ]);

        $this->assertSelectorTextContains('body', 'Cette adresse email est déjà utilisée.');
    }

    public function testRegisterRequiresTheCsrfToken(): void
    {
        $this->client->request('POST', '/register', ['username' => 'x', 'email' => 'x@example.com', 'password' => 'a', 'password_confirm' => 'a']);

        $this->assertSelectorTextContains('body', 'Token invalide');
        $this->assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'x@example.com']));
    }

    public function testForgottenPasswordSendsALinkThatWorksOnce(): void
    {
        $this->client->request('GET', '/forgot');
        $this->client->submitForm('Recevoir le lien', ['email' => 'demo@example.com']);

        $this->assertQueuedEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'to', 'demo@example.com');
        preg_match('#href="(http://localhost/reset\?token=[a-f0-9]{64})"#', $email->getHtmlBody(), $link);
        $this->assertNotEmpty($link, 'Le lien doit utiliser le domaine du site');

        // Seul le hash du token est stocké en base
        $this->assertNotSame(substr($link[1], -64), $this->user('demo@example.com')->getResetToken());

        $this->client->request('GET', $link[1]);
        $this->client->submitForm('Changer mon mot de passe', ['password' => 'nouveau123', 'password_confirm' => 'nouveau123']);
        $this->assertResponseRedirects('/login');

        // Le lien ne fonctionne plus une seconde fois
        $this->client->request('GET', $link[1]);
        $this->assertSelectorNotExists('input[name=password]');
    }

    public function testForgottenPasswordDoesNotRevealWhetherAnEmailExists(): void
    {
        $this->client->request('GET', '/forgot');
        $this->client->submitForm('Recevoir le lien', ['email' => 'inconnu@example.com']);

        $this->assertResponseIsSuccessful();
        $this->assertQueuedEmailCount(0);
    }

    public function testExpiredResetLinkIsRefused(): void
    {
        $user = $this->user('demo@example.com');
        $user->setResetToken(hash('sha256', 'ancien-token'));
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->em()->flush();

        $this->client->request('GET', '/reset?token=ancien-token');

        $this->assertSelectorNotExists('input[name=password]');
    }

    public function testProfileCanBeUpdated(): void
    {
        $this->loginAs('demo@example.com');
        $this->client->request('GET', '/profile');
        $this->client->submitForm('Enregistrer les modifications', ['username' => 'demo-modifie', 'email' => 'demo@example.com']);

        $this->client->followRedirect();
        $this->assertStringContainsString('Profil mis à jour', $this->flashes());
        $this->assertSame('demo-modifie', $this->user('demo@example.com')->getUsername());
    }

    public function testPasswordChangeRequiresTheCurrentPassword(): void
    {
        $this->loginAs('demo@example.com');
        $this->client->request('GET', '/profile');
        $this->client->submitForm('Changer le mot de passe', ['current_password' => 'faux', 'new_password' => 'nouveau123', 'new_password_confirm' => 'nouveau123']);

        $this->client->followRedirect();
        $this->assertStringContainsString('Mot de passe actuel incorrect', $this->flashes());
    }

    public function testGoogleButtonExplainsWhenItIsNotConfigured(): void
    {
        // Les identifiants Google ne sont pas définis en environnement de test
        $this->client->request('GET', '/connect/google');
        $this->client->followRedirect();

        $this->assertRouteSame('app_login');
        $this->assertStringContainsString('OAUTH_GOOGLE_CLIENT_ID', $this->flashes());
    }
}
