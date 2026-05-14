<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\CommentStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MovieController extends AbstractController
{
    #[Route('/detail/{id}', name: 'movie_detail')]
    public function detail(Media $media): Response
    {
        return $this->render('movie/detail.html.twig', [
            'media' => $media,
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
