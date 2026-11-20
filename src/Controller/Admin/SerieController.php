<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Episode;
use App\Entity\Season;
use App\Entity\Serie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des saisons et des épisodes d'une série.
 * Chaque formulaire de la page porte le même token CSRF « admin_seasons_{id de la série} ».
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/series/{id}')]
class SerieController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/seasons', name: 'admin_serie_seasons', methods: ['GET'])]
    public function seasons(Serie $serie): Response
    {
        return $this->render('admin/serie_seasons.html.twig', ['serie' => $serie]);
    }

    #[Route('/seasons/add', name: 'admin_season_add', methods: ['POST'])]
    public function addSeason(Serie $serie, Request $request): Response
    {
        if ($this->checkToken($serie, $request)) {
            $season = new Season();
            $season->setSerie($serie);
            $season->setNumber(trim($request->request->getString('number')) ?: 'Saison ' . ($serie->getSeasons()->count() + 1));
            $this->em->persist($season);
            $this->em->flush();
            $this->addFlash('success', "« {$season->getNumber()} » ajoutée.");
        }

        return $this->backToSeasons($serie);
    }

    #[Route('/seasons/{seasonId}/edit', name: 'admin_season_edit', methods: ['POST'])]
    public function editSeason(Serie $serie, int $seasonId, Request $request): Response
    {
        $season = $this->findSeason($serie, $seasonId);
        $number = trim($request->request->getString('number'));

        if ($this->checkToken($serie, $request) && $number !== '') {
            $season->setNumber($number);
            $this->em->flush();
            $this->addFlash('success', 'Saison renommée.');
        }

        return $this->backToSeasons($serie);
    }

    #[Route('/seasons/{seasonId}/delete', name: 'admin_season_delete', methods: ['POST'])]
    public function deleteSeason(Serie $serie, int $seasonId, Request $request): Response
    {
        $season = $this->findSeason($serie, $seasonId);

        if ($this->checkToken($serie, $request)) {
            $this->em->remove($season);
            $this->em->flush();
            $this->addFlash('success', "« {$season->getNumber()} » et ses épisodes ont été supprimés.");
        }

        return $this->backToSeasons($serie);
    }

    #[Route('/seasons/{seasonId}/episodes/add', name: 'admin_episode_add', methods: ['POST'])]
    public function addEpisode(Serie $serie, int $seasonId, Request $request): Response
    {
        $season = $this->findSeason($serie, $seasonId);

        if ($this->checkToken($serie, $request)) {
            $episode = new Episode();
            $episode->setSeason($season);
            if ($this->fillEpisode($episode, $request)) {
                $this->em->persist($episode);
                $this->em->flush();
                $this->addFlash('success', "Épisode « {$episode->getTitle()} » ajouté.");
            }
        }

        return $this->backToSeasons($serie, $season);
    }

    #[Route('/episodes/{episodeId}/edit', name: 'admin_episode_edit', methods: ['POST'])]
    public function editEpisode(Serie $serie, int $episodeId, Request $request): Response
    {
        $episode = $this->findEpisode($serie, $episodeId);

        if ($this->checkToken($serie, $request) && $this->fillEpisode($episode, $request)) {
            $this->em->flush();
            $this->addFlash('success', "Épisode « {$episode->getTitle()} » modifié.");
        }

        return $this->backToSeasons($serie, $episode->getSeason());
    }

    #[Route('/episodes/{episodeId}/delete', name: 'admin_episode_delete', methods: ['POST'])]
    public function deleteEpisode(Serie $serie, int $episodeId, Request $request): Response
    {
        $episode = $this->findEpisode($serie, $episodeId);
        $season = $episode->getSeason();

        if ($this->checkToken($serie, $request)) {
            $this->em->remove($episode);
            $this->em->flush();
            $this->addFlash('success', "Épisode « {$episode->getTitle()} » supprimé.");
        }

        return $this->backToSeasons($serie, $season);
    }

    /** Valide et applique titre, durée et date. Affiche un message et renvoie false si invalide. */
    private function fillEpisode(Episode $episode, Request $request): bool
    {
        $title = trim($request->request->getString('title'));
        $duration = $request->request->getInt('duration');
        $releasedAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('released_at'));

        if ($title === '' || $duration < 1 || !$releasedAt) {
            $this->addFlash('error', 'Épisode non enregistré : le titre, une durée (en minutes) et la date de sortie sont obligatoires.');
            return false;
        }

        $episode->setTitle(mb_substr($title, 0, 255));
        $episode->setDuration($duration);
        $episode->setReleasedAt($releasedAt);

        return true;
    }

    private function checkToken(Serie $serie, Request $request): bool
    {
        if ($this->isCsrfTokenValid('admin_seasons_' . $serie->getId(), $request->request->get('_token'))) {
            return true;
        }
        $this->addFlash('error', 'Le formulaire a expiré, veuillez réessayer.');

        return false;
    }

    // On vérifie que la saison / l'épisode appartient bien à la série de l'URL
    private function findSeason(Serie $serie, int $seasonId): Season
    {
        foreach ($serie->getSeasons() as $season) {
            if ($season->getId() === $seasonId) {
                return $season;
            }
        }
        throw $this->createNotFoundException('Saison introuvable pour cette série.');
    }

    private function findEpisode(Serie $serie, int $episodeId): Episode
    {
        foreach ($serie->getSeasons() as $season) {
            foreach ($season->getEpisodes() as $episode) {
                if ($episode->getId() === $episodeId) {
                    return $episode;
                }
            }
        }
        throw $this->createNotFoundException('Épisode introuvable pour cette série.');
    }

    private function backToSeasons(Serie $serie, ?Season $season = null): Response
    {
        $url = $this->generateUrl('admin_serie_seasons', ['id' => $serie->getId()]);

        return $this->redirect($season ? $url . '#saison-' . $season->getId() : $url);
    }
}
