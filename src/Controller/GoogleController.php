<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(
        ClientRegistry $clientRegistry,
        #[Autowire(env: 'OAUTH_GOOGLE_CLIENT_ID')] string $googleClientId,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        if (!$googleClientId) {
            $this->addFlash('error', 'La connexion Google n\'est pas configurée (OAUTH_GOOGLE_CLIENT_ID manquant dans .env.local).');
            return $this->redirectToRoute('app_login');
        }

        return $clientRegistry->getClient('google')->redirect(['openid', 'email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(): void
    {
        // Intercepté par App\Security\GoogleAuthenticator
    }
}
