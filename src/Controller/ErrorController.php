<?php
namespace Lalamefine\Autoadmin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class ErrorController extends AutoAdminAbstractController
{
    public function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if(!$request->headers->get('hx-request')){
            return parent::render('index.html.twig', [
                'content' => parent::renderView($view, $parameters)
            ], $response);
        }
        return parent::render($view, $parameters, $response);
    }

    public function showError(int $statusCode, ?Throwable $exception = null): Response
    {
        // Si aucune exception n'est passée, créer une générique
        if (!$exception) {
            $exception = new \Exception("Une erreur inattendue s'est produite (code: $statusCode)");
        }
        return $this->render('component/error.html.twig', [
            'error' => $exception,
        ]);
    }

    #[Route('/error/test', name: 'autoadmin_error_test')]
    public function testError(): void
    {
        // Méthode pour tester l'affichage d'erreur
        throw new \Exception('Ceci est une erreur de test pour vérifier l\'affichage.');
    }
}