<?php namespace Lalamefine\Autoadmin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lalamefine\Autoadmin\LalamefineAutoadminBundle;
use Lalamefine\Autoadmin\Service\EntityPrinter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

abstract class AutoAdminAbstractController extends AbstractController
{
    public function __construct(
        protected LalamefineAutoadminBundle $bundle,
        protected EntityManagerInterface $em,
        protected EntityPrinter $entityPrinter,
    ) {}

    protected function renderView(string $view, array $parameters = []): string
    {
        $loader = new FilesystemLoader($this->bundle->getPath().'/src/templates/');
        $twig = new Environment($loader);
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $twig->addGlobal('app', [
            'request' => $request,
        ]);
        $twig->addFunction(new \Twig\TwigFunction('asset', function ($path) {
            return '/autoadmin/public/'.$path;
        }));
        $twig->addFunction(new \Twig\TwigFunction('path', function ($route, $params = []) {
            $router = $this->container->get('router');
            return $router->generate($route, $params);
        }));
        $twig->addFunction(new \Twig\TwigFunction('dump', function ($var) {
            return var_dump($var);
        }));
        return $twig->render($view, $parameters);
    }

    public function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $content = $this->renderView($view, $parameters);
        if ($response === null) {
            $response = new Response();
        }
        $response->setContent($content);
        return $response;
    }
}