<?php

namespace Pentatrion\UploadBundle\EventSubscriber;

use Pentatrion\UploadBundle\Exception\InformativeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof InformativeException) {
            return;
        }

        $this->logger->notice($exception->getMessage());
        $response = new JsonResponse([
            'title' => $exception->getMessage(),
            'status' => $exception->getStatusCode()
        ]);
        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException'
        ];
    }
}
