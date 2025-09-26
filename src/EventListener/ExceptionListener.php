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
            return; // Ne pas intercepter les erreurs hors du bundle
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // Rendre la réponse d'erreur en utilisant le contrôleur d'erreur
        $response = $this->errorController->showError($statusCode, $exception);
        $event->setResponse($response);
    }
}