<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\Movie;
use App\Entity\Serie;
use App\Enum\CommentStatusEnum;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\LanguageRepository;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(MediaRepository $mediaRepo, UserRepository $userRepo): Response
    {
        return $this->render('admin/admin.html.twig', [
            'totalMedias' => count($mediaRepo->findAll()),
            'totalUsers'  => count($userRepo->findAll()),
        ]);
    }

    #[Route('/admin/movies', name: 'admin_movies')]
    public function listMovies(MediaRepository $repository): Response
    {
        return $this->render('admin/admin_films.html.twig', [
            'medias' => $repository->findAll(),
        ]);
    }

    #[Route('/admin/movies/add', name: 'admin_movies_add', methods: ['GET', 'POST'])]
    public function addMovie(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository,
        LanguageRepository $languageRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $type  = $request->request->get('type');
            $media = $type === 'serie' ? new Serie() : new Movie();

            $media->setTitle($request->request->get('title', ''));
            $media->setShortDescription($request->request->get('short_description', ''));
            $media->setLongDescription($request->request->get('long_description', ''));
            $media->setCoverImage($request->request->get('cover', ''));
            $media->setStaff([]);
            $media->setCasting([]);

            $releaseDate = $request->request->get('release_date');
            $media->setReleaseDate($releaseDate ? new \DateTime($releaseDate) : new \DateTime());

            foreach ($request->request->all('categories') as $categoryId) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $media->addCategory($category);
                }
            }

            foreach ($request->request->all('languages') as $languageId) {
                $language = $languageRepository->find($languageId);
                if ($language) {
                    $media->addLanguage($language);
                }
            }

            $em->persist($media);
            $em->flush();

            $this->addFlash('success', 'Média ajouté avec succès.');
            return $this->redirectToRoute('admin_movies');
        }

        return $this->render('admin/admin_add_films.html.twig', [
            'categories' => $categoryRepository->findAll(),
            'languages'  => $languageRepository->findAll(),
        ]);
    }

    #[Route('/admin/movies/{id}/delete', name: 'admin_movies_delete', methods: ['POST'])]
    public function deleteMedia(Media $media, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_media_' . $media->getId(), $request->request->get('_token'))) {
            $em->remove($media);
            $em->flush();
            $this->addFlash('success', 'Média supprimé.');
        }

        return $this->redirectToRoute('admin_movies');
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function listUsers(UserRepository $repository): Response
    {
        return $this->render('admin/admin_users.html.twig', [
            'users' => $repository->findAll(),
        ]);
    }

    #[Route('/admin/categories', name: 'admin_categories')]
    public function listsCategories(CategoryRepository $repository): Response
    {
        return $this->render('admin/admin_categories.html.twig', [
            'categories' => $repository->findAll(),
        ]);
    }

    #[Route('/admin/comments', name: 'admin_comments')]
    public function listComments(CommentRepository $repository): Response
    {
        return $this->render('admin/admin_comments.html.twig', [
            'comments' => $repository->findAll(),
        ]);
    }

    #[Route('/admin/comments/{id}/moderate', name: 'admin_comment_moderate', methods: ['POST'])]
    public function moderateComment(Comment $comment, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('moderate_comment_' . $comment->getId(), $request->request->get('_token'))) {
            $action = $request->request->get('action');
            $comment->setStatus($action === 'validate' ? CommentStatusEnum::VALID : CommentStatusEnum::REJECTED);
            $em->flush();
        }

        return $this->redirectToRoute('admin_comments');
    }
}
