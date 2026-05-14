<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Media;
use App\Entity\Playlist;
use App\Entity\PlaylistMedia;
use App\Entity\User;
use App\Repository\MediaRepository;
use App\Repository\PlaylistMediaRepository;
use App\Repository\PlaylistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MyListController extends AbstractController
{
    #[Route('/lists', name: 'show_my_list')]
    public function myLists(Request $request, PlaylistRepository $playlistRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $playlistId = $request->query->get('playlist');
        $selectedPlaylist = $playlistId ? $playlistRepository->find($playlistId) : null;

        return $this->render('lists.html.twig', [
            'myPlaylists'        => $user->getPlaylists(),
            'subscribedPlaylists' => $user->getPlaylistSubscriptions()
                ->map(fn($ps) => $ps->getPlaylist()),
            'selectedPlaylist'   => $selectedPlaylist,
        ]);
    }

    #[Route('/lists/create', name: 'playlist_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $name = trim($request->request->get('name', ''));

        if ($name) {
            $playlist = new Playlist();
            $playlist->setName($name);
            $playlist->setCreator($user);
            $playlist->setCreatedAt(new \DateTimeImmutable());
            $playlist->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($playlist);
            $em->flush();

            $this->addFlash('success', "Playlist « {$name} » créée.");
        }

        return $this->redirectToRoute('show_my_list');
    }

    #[Route('/lists/{playlistId}/add/{mediaId}', name: 'playlist_add_media', methods: ['POST'])]
    public function addMedia(
        int $playlistId,
        int $mediaId,
        Request $request,
        EntityManagerInterface $em,
        PlaylistRepository $playlistRepository,
        MediaRepository $mediaRepository,
        PlaylistMediaRepository $playlistMediaRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $playlist = $playlistRepository->find($playlistId);
        $media = $mediaRepository->find($mediaId);

        if (!$playlist || $playlist->getCreator() !== $user || !$media) {
            throw $this->createNotFoundException();
        }

        $alreadyIn = $playlistMediaRepository->findOneBy([
            'playlist' => $playlist,
            'media'    => $media,
        ]);

        if (!$alreadyIn) {
            $pm = new PlaylistMedia();
            $pm->setPlaylist($playlist);
            $pm->setMedia($media);
            $pm->setAddedAt(new \DateTimeImmutable());
            $em->persist($pm);

            $playlist->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', "Ajouté à « {$playlist->getName()} ».");
        } else {
            $this->addFlash('success', "Ce média est déjà dans la playlist.");
        }

        $redirect = $request->request->get('redirect', 'show_my_list');
        if ($redirect === 'detail') {
            return $this->redirectToRoute('movie_detail', ['id' => $mediaId]);
        }

        return $this->redirectToRoute('show_my_list', ['playlist' => $playlistId]);
    }

    #[Route('/lists/{playlistId}/remove/{mediaId}', name: 'playlist_remove_media', methods: ['POST'])]
    public function removeMedia(
        int $playlistId,
        int $mediaId,
        Request $request,
        EntityManagerInterface $em,
        PlaylistRepository $playlistRepository,
        PlaylistMediaRepository $playlistMediaRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $playlist = $playlistRepository->find($playlistId);

        if (!$playlist || $playlist->getCreator() !== $user) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('remove_media_' . $mediaId, $request->request->get('_token'))) {
            $pm = $playlistMediaRepository->findOneBy([
                'playlist' => $playlist,
                'media'    => $mediaId,
            ]);
            if ($pm) {
                $em->remove($pm);
                $em->flush();
                $this->addFlash('success', 'Média retiré de la playlist.');
            }
        }

        return $this->redirectToRoute('show_my_list', ['playlist' => $playlistId]);
    }

    #[Route('/lists/{id}/delete', name: 'playlist_delete', methods: ['POST'])]
    public function delete(Playlist $playlist, EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($playlist->getCreator() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_playlist_' . $playlist->getId(), $request->request->get('_token'))) {
            $em->remove($playlist);
            $em->flush();
            $this->addFlash('success', 'Playlist supprimée.');
        }

        return $this->redirectToRoute('show_my_list');
    }
}
