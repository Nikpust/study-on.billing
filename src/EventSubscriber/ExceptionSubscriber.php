<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onJsonException',
        ];
    }

    public function onJsonException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'Internal Server Error';

        if ($throwable instanceof HttpExceptionInterface) {
            $statusCode = $throwable->getStatusCode();
            $message = $throwable->getMessage() ?: (Response::$statusTexts[$statusCode] ?? 'Error');
        }
        if ($statusCode >= 500) {
            $message = 'Internal Server Error';
        }

        $event->setResponse(new JsonResponse([
            'code' => $statusCode,
            'message' => $message,
        ], $statusCode));
    }
}
