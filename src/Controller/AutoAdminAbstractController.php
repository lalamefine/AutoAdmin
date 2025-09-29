<?php namespace Lalamefine\Autoadmin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lalamefine\Autoadmin\LalamefineAutoadminBundle;
use Lalamefine\Autoadmin\Service\EntityPrinter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Lalamefine\Autoadmin\Service\TwigBundleService;

abstract class AutoAdminAbstractController extends AbstractController
{
    public function __construct(
        protected LalamefineAutoadminBundle $bundle,
        protected EntityManagerInterface $em,
        protected EntityPrinter $entityPrinter,
        protected TwigBundleService $twigService,
    ) {}

    protected function renderView(string $view, array $parameters = []): string
    {
        return $this->twigService->getEnv()->render($view, $parameters);
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