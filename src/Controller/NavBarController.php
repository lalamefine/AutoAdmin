<?php namespace Lalamefine\Autoadmin\Controller;

use Lalamefine\Autoadmin\Controller\AutoAdminAbstractController;
use Lalamefine\Autoadmin\Service\EntityManipulator;
use Symfony\Component\Routing\Annotation\Route;

class NavBarController extends AutoAdminAbstractController
{
    #[Route('/navbar', name: 'autoadmin_navbar')]
    public function navbar(EntityManipulator $entityManipulator)
    {
        $entities = $entityManipulator->listEntities();
        return $this->render('navbar.html.twig', [
            'items' => $this->buildTree($entities)
        ]);
    }

    
    // shape : ['CategoryA' => ['Entity1' => 'FCQN', 'Entity2' => 'FCQN'], 'OtherCategoryB' => ['Entity3' => 'FCQN','SubcategoryB1' => ['Entity4' => 'FCQN', 'Entity5' => 'FCQN']], 'Entity6' => 'FCQN']
    private function buildTree(array &$entities):array
    {
        $entitiesTree = [];
        foreach ($entities as $entity) {
            $this->insertIntoTree($entity, $entity, $entitiesTree);
        }
        $this->simplifyTree($entitiesTree);
        return $entitiesTree;
    }

    private function insertIntoTree(string $processingName, string $sfqcn, array &$tree): void
    {
        $parts = explode('\\', $processingName);
        $part = array_shift($parts);
        if (!isset($tree[$part])) {
            $tree[$part] = [];
        }
        if (count($parts) > 0) {
            $this->insertIntoTree(implode('\\', $parts), $sfqcn, $tree[$part]);
        } else {
            // Leaf node
            $tree[$part] = $sfqcn;
        }
    }

    private function simplifyTree(array &$tree): void
    {
        foreach ($tree as $key => $_) {
            if (is_array($tree[$key])) {
                $this->simplifyTree($tree[$key]);
                // If the array has only one element and that element is not an array, replace it with that element
                if (count( $tree[$key]) === 1) {
                    $subKey = array_key_first($tree[$key]);
                    $newKey = $key.'\\'.$subKey;
                    $tree[$newKey] = $tree[$key][$subKey];
                    unset($tree[$key]);
                }
            }
        }
    }

}