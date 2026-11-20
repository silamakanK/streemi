<?php

declare(strict_types=1);

namespace App\Tests\Functional;

class SecurityTest extends AppWebTestCase
{
    /** @dataProvider memberPages */
    public function testVisitorsAreSentToTheLoginPage(string $url): void
    {
        $this->client->request('GET', $url);

        $this->assertResponseRedirects('/login');
    }

    public static function memberPages(): array
    {
        return [['/lists'], ['/history'], ['/profile'], ['/admin'], ['/admin/movies']];
    }

    /** @dataProvider adminPages */
    public function testUsersCannotOpenTheBackOffice(string $method, string $url): void
    {
        $this->loginAs('demo@example.com');

        $this->client->request($method, $url);

        $this->assertResponseStatusCodeSame(403);
    }

    public static function adminPages(): array
    {
        return [
            ['GET', '/admin'],
            ['GET', '/admin/movies'],
            ['GET', '/admin/movies/add'],
            ['GET', '/admin/categories'],
            ['GET', '/admin/users'],
            ['GET', '/admin/comments'],
            ['POST', '/admin/categories/add'],
            ['POST', '/admin/users/1/toggle-admin'],
        ];
    }

    public function testAdminsCanOpenTheBackOffice(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', '24 animés');
    }

    public function testPublicPagesWorkWithoutAccount(): void
    {
        foreach (['/', '/discover', '/search?q=akira', '/subscriptions', '/login', '/register'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
        }
    }

    public function testUnknownMediaShowsTheCustom404Page(): void
    {
        $this->client->request('GET', '/detail/999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
