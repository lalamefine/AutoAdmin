<?php namespace Lalamefine\Autoadmin\Service;

use Lalamefine\Autoadmin\LalamefineAutoadminBundle;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TwigBundleService {
    private \Twig\Environment $twig;

    public function __construct(
        private LalamefineAutoadminBundle $bundle,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator
    ) {
        $loader = new \Twig\Loader\FilesystemLoader($this->bundle->getPath().'/src/templates/');
        $this->twig = new \Twig\Environment($loader);
        $this->twig->addGlobal('app', [
            'request' =>  $this->requestStack->getCurrentRequest(),
        ]);
        $this->twig->addFunction(new \Twig\TwigFunction('asset', function ($path) {
            return '/autoadmin/public/'.$path;
        }));
        $this->twig->addFunction(new \Twig\TwigFunction('path', function ($route, $params = []) {
            return $this->urlGenerator->generate($route, $params);
        }));
        $this->twig->addFunction(new \Twig\TwigFunction('dump', function ($var) {
            return var_dump($var);
        }));
    }

    public function getEnv(): \Twig\Environment {
        return $this->twig;
    }
}