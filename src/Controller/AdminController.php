<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use App\Enum\CatalogTypeEnum;
use App\Enum\CommentStatusEnum;
use App\Repository\CommentRepository;
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
            'totalMedias' => $mediaRepo->count([]),
            'totalMovies' => count($mediaRepo->findForAdmin('', CatalogTypeEnum::MOVIE, 1, 1)),
            'totalSeries' => count($mediaRepo->findForAdmin('', CatalogTypeEnum::SERIE, 1, 1)),
            'totalAnimes' => count($mediaRepo->findForAdmin('', CatalogTypeEnum::ANIME, 1, 1)),
            'totalUsers'  => $userRepo->count([]),
        ]);
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function listUsers(UserRepository $repository): Response
    {
        return $this->render('admin/admin_users.html.twig', [
            'users' => $repository->findAll(),
        ]);
    }

    #[Route('/admin/users/{id}/toggle-admin', name: 'admin_user_toggle_admin', methods: ['POST'])]
    public function toggleAdmin(User $user, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('toggle_admin_' . $user->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_users');
        }

        // Évite qu'un administrateur se retire ses propres droits par erreur
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier vos propres droits.');
            return $this->redirectToRoute('admin_users');
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $user->removeRole('ROLE_ADMIN');
            $this->addFlash('success', "{$user->getUsername()} n'est plus administrateur.");
        } else {
            $user->addRole('ROLE_ADMIN');
            $this->addFlash('success', "{$user->getUsername()} est maintenant administrateur.");
        }
        $em->flush();

        return $this->redirectToRoute('admin_users');
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
