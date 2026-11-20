<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MovieAssistant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AssistantController extends AbstractController
{
    private const SESSION_KEY = 'assistant_history';
    // Limite la taille de la conversation envoyée à l'API (coût et rapidité)
    private const MAX_HISTORY = 20;
    private const MAX_MESSAGE_LENGTH = 500;

    #[Route('/assistant/message', name: 'assistant_message', methods: ['POST'])]
    public function message(Request $request, MovieAssistant $assistant): JsonResponse
    {
        if (!$this->isCsrfTokenValid('assistant', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Session expirée, recharge la page.'], 403);
        }

        $content = trim((string) ($request->toArray()['message'] ?? ''));
        if ($content === '') {
            return $this->json(['error' => 'Message vide.'], 400);
        }
        $content = mb_substr($content, 0, self::MAX_MESSAGE_LENGTH);

        $session = $request->getSession();
        $history = $session->get(self::SESSION_KEY, []);
        $history[] = ['role' => 'user', 'content' => $content];
        $history = array_slice($history, -self::MAX_HISTORY);
        // La conversation doit commencer par un message utilisateur
        while ($history && $history[0]['role'] !== 'user') {
            array_shift($history);
        }

        /** @var User $user */
        $user = $this->getUser();
        $answer = $assistant->ask($history, $user);

        // On garde les titres proposés dans l'historique pour que l'assistant s'en souvienne
        $memory = $answer['reply'];
        if ($answer['medias']) {
            $memory .= "\n(Proposés : " . implode(', ', array_map(
                fn($media) => sprintf('%s [id %d]', $media->getTitle(), $media->getId()),
                $answer['medias']
            )) . ')';
        }
        $history[] = ['role' => 'assistant', 'content' => $memory];
        $session->set(self::SESSION_KEY, $history);

        return $this->json([
            'reply' => $answer['reply'],
            'medias' => array_map(fn($media) => [
                'title' => $media->getTitle(),
                'cover' => $media->getCoverImage(),
                'url' => $this->generateUrl('movie_detail', ['id' => $media->getId()]),
                'type' => MovieAssistant::typeLabel($media),
                'year' => $media->getReleaseDate()?->format('Y'),
            ], $answer['medias']),
        ]);
    }

    #[Route('/assistant/reset', name: 'assistant_reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('assistant', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Session expirée, recharge la page.'], 403);
        }

        $request->getSession()->remove(self::SESSION_KEY);

        return $this->json(['ok' => true]);
    }
}
