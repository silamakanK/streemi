<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/admin.html.twig');
    }

    #[Route('/admin/movies', name: 'admin_movies')]
    public function listMovies(): Response
    {
        return $this->render('admin/admin_films.html.twig');
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function listUsers(): Response
    {
        return $this->render('admin/admin_users.html.twig');
    }

    #[Route('/admin/categories', name: 'admin_categories')]
    public function listsCategories(): Response
    {
        return $this->render('admin/admin_categories.html.twig');
    }

    #[Route('/admin/comments', name: 'admin_comments')]
    public function listComments(): Response
    {
        return $this->render('admin/admin_comments.html.twig');
    }

    #[Route('/admin/movies/add', name: 'admin_movies_add')]
    public function addMovie(): Response
    {
        return $this->render('admin/admin_add_films.html.twig');
    }
}