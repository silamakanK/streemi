<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Playlist;
use App\Entity\User;
use App\Repository\PlaylistRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MyListController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/lists', name: 'show_my_list')]
    public function myLists(
        UserRepository $repository,
        Request $request,
        PlaylistRepository $playlistRepository
    ): Response
    {
        $playlistId = $request->query->get('playlist');
        if ($playlistId) {
            $selectedPlaylist = $playlistRepository->find($playlistId);
        } else {
            $selectedPlaylist = null;
        }

        $user = $repository->findOneBy([]);

        $playlists = $user->getPlaylists();
        $subscribedPlaylist = $user->getPlaylistSubscriptions()
            ->map(fn($playlistSubscription) => $playlistSubscription->getPlaylist());

        return $this->render('lists.html.twig', [
            'myPlaylists' => $playlists,
            'subscribedPlaylists' => $subscribedPlaylist,
            'selectedPlaylist' => $selectedPlaylist,
        ]);
    }
}