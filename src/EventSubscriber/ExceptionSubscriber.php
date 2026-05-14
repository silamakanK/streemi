<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private Environment $twig) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $statusCode = $exception->getStatusCode();

        $template = match ($statusCode) {
            404 => 'errors/404.html.twig',
            403 => 'errors/403.html.twig',
            default => 'errors/error.html.twig',
        };

        $content = $this->twig->render($template, [
            'status_code' => $statusCode,
            'message'     => $exception->getMessage() ?: 'Une erreur est survenue.',
        ]);

        $event->setResponse(new Response($content, $statusCode));
    }
}
