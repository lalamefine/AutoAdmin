<?php namespace Lalamefine\Autoadmin\EventListener;

use Lalamefine\Autoadmin\Controller\ErrorController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Twig\Environment;

class ExceptionListener
{
    private ErrorController $errorController;
    private Environment $twig;

    public function __construct(Environment $twig, ErrorController $errorController)
    {
        $this->twig = $twig;
        $this->errorController = $errorController;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $routeName = $event->getRequest()->attributes->get('_route');
        if (!$routeName || !str_starts_with($routeName, 'autoadmin_')) {
            return; // Do not handle exceptions for non-autoadmin routes
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // Render the error response using the error controller
        $response = $this->errorController->showError($statusCode, $exception);
        $event->setResponse($response);
    }
}