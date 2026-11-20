<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        return $clientRegistry->getClient('google')->redirect(['openid', 'email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(): void
    {
        // Intercepté par App\Security\GoogleAuthenticator
    }
}
