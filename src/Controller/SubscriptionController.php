<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    #[Route('/subscriptions', name: 'show_subscriptions')]
    public function listSubscriptions(SubscriptionRepository $repository): Response
    {
        $subscriptions = $repository->findAll();
        return $this->render('subscription.html.twig', [
            'subscriptions' => $subscriptions,
        ]);
    }
}