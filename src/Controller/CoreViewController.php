<?php namespace Lalamefine\Autoadmin\Controller;

use Lalamefine\Autoadmin\Controller\AutoAdminAbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mime\MimeTypes;

class CoreViewController extends AutoAdminAbstractController
{
    #[Route('/index', name: 'autoadmin_index')]
    public function index()
    {
        return $this->render('index.html.twig');
    }

    #[Route('/public/{path}', name: 'autoadmin_public', requirements: ['path' => '.*'])]
    public function public(string $path)
    {
        $filepath = $this->bundle->getPath()."/public/$path";
        return new BinaryFileResponse($filepath, 200);
    }

}