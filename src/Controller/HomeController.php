<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\CatalogTypeEnum;
use App\Repository\MediaRepository;
use App\Service\RecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function __invoke(
        Request $request,
        MediaRepository $repository,
        RecommendationService $recommendationService,
    ): Response
    {
        $type = CatalogTypeEnum::tryFrom($request->query->getString('type')) ?? CatalogTypeEnum::MOVIE;

        /** @var User|null $user */
        $user = $this->getUser();

        // Bandeau : le dernier média regardé de cet onglet, sinon le plus récent
        $lastWatched = $user ? $repository->findLastWatchedBy($user, $type) : null;

        return $this->render('index.html.twig', [
            'type'            => $type,
            'medias'          => $repository->findPopular($type),
            'lastWatched'     => $lastWatched ?? $repository->findLatest($type),
            'isResume'        => $lastWatched !== null,
            'recommendations' => $recommendationService->recommend($user, $type),
        ]);
    }
}
