<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'search')]
    public function search(Request $request, MediaRepository $repository): Response
    {
        $query   = trim($request->query->get('q', ''));
        $results = $query ? $repository->search($query) : [];

        return $this->render('search.html.twig', [
            'query'   => $query,
            'results' => $results,
        ]);
    }
}
