<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PlaylistRepository;
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
        Request $request,
        PlaylistRepository $playlistRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $playlistId = $request->query->get('playlist');
        $selectedPlaylist = $playlistId ? $playlistRepository->find($playlistId) : null;

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
