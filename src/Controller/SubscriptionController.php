<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SubscriptionController extends AbstractController
{
    #[Route('/subscriptions', name: 'show_subscriptions')]
    public function listSubscriptions(SubscriptionRepository $repository): Response
    {
        return $this->render('subscription.html.twig', [
            'subscriptions' => $repository->findAll(),
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/subscriptions/{id}/subscribe', name: 'subscription_subscribe', methods: ['POST'])]
    public function subscribe(Subscription $subscription, EntityManagerInterface $em, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('subscribe_' . $subscription->getId(), $request->request->get('_token'))) {
            $user->setCurrentSubscription($subscription);
            $em->flush();
            $this->addFlash('success', "Vous êtes maintenant abonné à « {$subscription->getName()} ».");
        }

        return $this->redirectToRoute('show_subscriptions');
    }
}
