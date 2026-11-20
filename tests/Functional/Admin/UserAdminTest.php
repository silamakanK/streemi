<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\AppWebTestCase;

class UserAdminTest extends AppWebTestCase
{
    public function testPromoteThenDemoteAnAdmin(): void
    {
        $this->loginAsAdmin();
        $this->client->followRedirects();
        $user = $this->user('demo@example.com');
        $form = fn() => $this->client->getCrawler()->filter('form[action$="/users/' . $user->getId() . '/toggle-admin"]')->form();

        $this->client->request('GET', '/admin/users');
        $this->client->submit($form());
        $this->assertContains('ROLE_ADMIN', $this->reload($user)->getRoles());

        $this->client->submit($form());
        $this->assertNotContains('ROLE_ADMIN', $this->reload($user)->getRoles());
        // Les rôles restent une liste JSON après un retrait
        $this->assertSame(['ROLE_USER'], array_values($this->reload($user)->getRoles()));
    }

    public function testAnAdminCannotChangeTheirOwnRights(): void
    {
        $admin = $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/users');
        $this->assertCount(0, $crawler->filter('form[action$="/users/' . $admin->getId() . '/toggle-admin"]'));

        // Aucun formulaire n'existe pour soi-même : on génère le token dans la session du navigateur de test
        $request = $this->client->getRequest();
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);
        $token = static::getContainer()->get('security.csrf.token_manager')->getToken('toggle_admin_' . $admin->getId())->getValue();
        $request->getSession()->save();
        $requestStack->pop();
        $this->client->request('POST', '/admin/users/' . $admin->getId() . '/toggle-admin', ['_token' => $token]);
        $this->client->followRedirect();

        $this->assertStringContainsString('propres droits', $this->flashes());
        $this->assertContains('ROLE_ADMIN', $this->reload($admin)->getRoles());
    }
}
