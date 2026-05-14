<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\User;
use App\Entity\WatchHistory;
use App\Enum\CommentStatusEnum;
use App\Repository\WatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MovieController extends AbstractController
{
    #[Route('/detail/{id}', name: 'movie_detail')]
    public function detail(Media $media, WatchHistoryRepository $watchHistoryRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $watchEntry = null;
        if ($user) {
            $watchEntry = $watchHistoryRepository->findByWatcherAndMedia($user->getId(), $media->getId());
        }

        return $this->render('movie/detail.html.twig', [
            'media'      => $media,
            'playlists'  => $user ? $user->getPlaylists() : [],
            'watchEntry' => $watchEntry,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/watch/{id}', name: 'media_watch', methods: ['POST'])]
    public function watch(Media $media, EntityManagerInterface $em, WatchHistoryRepository $watchHistoryRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $entry = $watchHistoryRepository->findByWatcherAndMedia($user->getId(), $media->getId());

        if ($entry) {
            $entry->setLastWatchedAt(new \DateTimeImmutable());
            $entry->setNumberOfViews($entry->getNumberOfViews() + 1);
        } else {
            $entry = new WatchHistory();
            $entry->setWatcher($user);
            $entry->setMedia($media);
            $entry->setLastWatchedAt(new \DateTimeImmutable());
            $entry->setNumberOfViews(1);
            $em->persist($entry);
        }

        $em->flush();

        return $this->redirectToRoute('movie_detail', ['id' => $media->getId()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/history', name: 'watch_history')]
    public function history(WatchHistoryRepository $watchHistoryRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $history = $watchHistoryRepository->findByWatcherOrderedByDate($user->getId());

        return $this->render('history.html.twig', [
            'history' => $history,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/detail/{id}/comment', name: 'media_comment_add', methods: ['POST'])]
    public function addComment(Media $media, Request $request, EntityManagerInterface $em): Response
    {
        $content = trim($request->request->get('content', ''));

        if ($content) {
            /** @var User $user */
            $user = $this->getUser();

            $comment = new Comment();
            $comment->setContent($content);
            $comment->setPublisher($user);
            $comment->setMedia($media);
            $comment->setStatus(CommentStatusEnum::WAITING);

            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Commentaire soumis, il sera visible après modération.');
        }

        return $this->redirectToRoute('movie_detail', ['id' => $media->getId()]);
    }
}
