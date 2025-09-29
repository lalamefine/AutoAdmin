<?php namespace Lalamefine\Autoadmin\Controller;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Lalamefine\Autoadmin\Controller\AutoAdminAbstractController;
use Lalamefine\Autoadmin\Service\AssociationManager;
use Lalamefine\Autoadmin\Service\EntityIdentifier;
use Lalamefine\Autoadmin\Service\EntityManipulator;
use Lalamefine\Autoadmin\Service\EntityPrinter;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EntityCRUDController extends AutoAdminAbstractController
{
    const ITEMS_PER_PAGE = 25;
    const MAX_STRING_LENGTH_TABLE = 20;
    const MAX_STRING_LENGTH_VIEW = 100;
    
    /*
    * Override the render method to use a base template and handle AJAX requests
    */
    public function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if(!$request->headers->get('hx-request')){;
            return parent::render('index.html.twig', [
                'content' => parent::renderView($view, $parameters)
            ], $response);
        }
        return new Response(
            '<div id="content" hx-swap-oob="true">'.
            parent::renderView($view, $parameters) 
            .'</div>'
        , $response?->getStatusCode() ?? 200, $response?->headers->all() ?? []);
    }

    #[Route('/entities/l/{fqcn}', name: 'autoadmin_entity_list', requirements: ['fqcn' => '.+'])]
    public function list(string $fqcn, EntityManipulator $entityManipulator, Request $request, EntityPrinter $entityPrinter, AssociationManager $associationManager): Response{
        $fqcn = urldecode($fqcn);
        $perPage = $request->query->getInt('per_page', self::ITEMS_PER_PAGE);
        $page = max(1, (int)$request->query->get('page', 1));
        $classMetadata = $entityManipulator->getClassMetadata($fqcn);
        $qb = $this->em->createQueryBuilder()->addSelect("e")->from($fqcn, "e");
        $i = 0;
        $mappings = $associationManager->getMappingToOneForClass($fqcn);
        foreach($mappings as $field => $mapping){
            $letters = substr($field, 0, 3);
            $qb->leftJoin("e.{$field}", "{$letters}_{$i}");
            $qb->addSelect("{$letters}_{$i}");
            $i++;
        }
        // Apply filters from query parameters
        foreach($request->query->all() as $key => $value){
            if(str_starts_with($key, 'f_') && $value !== ''){
                $field = substr($key, 2);
                if(in_array($field, $classMetadata->getFieldNames())){
                    $paramName = 'param_'.$field;
                    if($value === 'null'){
                        $qb->andWhere("e.{$field} IS NULL");
                        continue;
                    } else if(in_array($classMetadata->getTypeOfField($field), ['integer', 'float', 'boolean', 'decimal'])){
                        $qb->andWhere("e.{$field} = :{$paramName}");
                        $qb->setParameter($paramName, $value);
                    } else if(in_array($classMetadata->getTypeOfField($field), ['string', 'text', 'guid'])){
                        $qb->andWhere("e.{$field} LIKE :{$paramName}");
                        $qb->setParameter($paramName, '%'.$value.'%');
                    } else{
                        throw new \Exception("Filtering not supported for field type ".$classMetadata->getTypeOfField($field));
                    }
                }
            }
        }
        $paginator = new Paginator($qb);
        $paginator->getQuery()
            ->setFirstResult($perPage * ($page - 1)) // Offset
            ->setMaxResults($perPage); // Limit
        $results = [...$paginator->getIterator()];

        $headers = array_merge($classMetadata->getFieldNames(), array_keys($mappings));
        $filterables = array_filter( $headers, fn($field) => in_array($classMetadata->getTypeOfField($field), [
            'string', 'text', 'guid', 'integer', 'float', 'boolean', 'decimal'
        ]));
        return $this->render('entity/list.html.twig', [
            'fqcn' => $fqcn,
            'identifier' => $classMetadata->getIdentifier()[0] ?? null,
            'headers' => $headers,
            'filterables' => $filterables,
            'nbItems' => count($paginator),
            'currentPage' => $page,
            'totalPages' => ceil(count($paginator) / $perPage),
            'perPage' => $perPage,
            'items' => array_map(function($item) use ($entityPrinter, $headers) {
                return $entityPrinter->printEntityRow($item, $headers, self::MAX_STRING_LENGTH_TABLE);
            }, $results)
        ]);
    }

    #[Route('/entity/r/{fqcn}/{id}', name: 'autoadmin_entity_view', requirements: ['fqcn' => '.*'])]
    public function view(string $fqcn, mixed $id, EntityManipulator $entityManipulator, EntityPrinter $entityPrinter): Response
    {
        $fqcn = urldecode($fqcn);
        $classMetadata = $this->em->getClassMetadata($fqcn);
        $entity = $entityManipulator->getEntity($fqcn, $id, true);
        if (!$entity) {
            throw $this->createNotFoundException();
        }
        return $this->render('entity/read.html.twig', [
            'entity' => $entityPrinter->printableEntityAr($entity, $fqcn, self::MAX_STRING_LENGTH_VIEW),
            'id' => $id,
            'identifier' => $classMetadata->getIdentifier()[0],
            'fqcn' => $fqcn
        ]);
    }

    #[Route('/entity/u/{fqcn}/{id}', name: 'autoadmin_entity_update', requirements: ['fqcn' => '.+'])]
    public function update(string $fqcn, mixed $id, EntityManipulator $entityManipulator, EntityPrinter $entityPrinter, Request $request, AssociationManager $associationManager, EntityIdentifier $entityIdentifier): Response
    {
        $fqcn = urldecode($fqcn);
        $classMetadata = $this->em->getClassMetadata($fqcn);
        // Handle form submission
        if($request->isMethod('POST')){
            $data = $request->request->all();
            if ($id == 'new') {
                $originEntity = (new ReflectionClass($fqcn))->newInstanceWithoutConstructor();
                $this->em->persist($originEntity);
            }else{
                $originEntity = $this->em->find($fqcn, $id);
            }
            foreach($data as $key => $value){
                if(isset($data[$key.'_null']) && $data[$key.'_null']){
                    $data[$key] = null;
                    unset($data[$key.'_null'] );
                }
            }
            // Collection management (many-to-many and one-to-many associations)
            if ($id != 'new') { // only for existing entities
                if(isset($data['remove'])){
                    foreach($data['remove'] as $toRemove){
                        [$field, $refId] = explode('/', $toRemove);
                        $originCollection = $entityManipulator->getCollection($originEntity, $field);
                        $originCollection = $originCollection->filter(fn($e) => $entityIdentifier->getEntityId($e) != $refId);
                        $classMetadata->setFieldValue($originEntity, $field, $originCollection);
                        $this->em->persist($originEntity);
                    }
                    unset($data['remove']);
                }
                if(isset($data['add'])){
                    foreach($data['add'] as $toAdd){
                        [$field, $refId] = explode('/', $toAdd);
                        $originEntity = $this->em->find($fqcn, $id);
                        $targetEntity = $this->em->find($classMetadata->getAssociationMapping($field)['targetEntity'], $refId);
                        $originCollection = $entityManipulator->getCollection($originEntity, $field);
                        $originCollection->add($targetEntity);
                        $classMetadata->setFieldValue($originEntity, $field, $originCollection);
                        $this->em->persist($originEntity);
                    }
                    unset($data['add']);
                }
            }
            // Update entity from form data
            $entityManipulator->updateEntityFromArray($originEntity, $data); // $this->em->flush(); is called inside
            if($id == 'new'){
                $id = $entityIdentifier->getEntityId($originEntity);
            }
            return $this->redirectToRoute('autoadmin_entity_view', ['fqcn' => $fqcn, 'id' => $id]);
        }
        // Get entity data as array
        if ($id == 'new') {
            $entityArray = array_fill_keys($classMetadata->getFieldNames(), null);
            $filteredAssociations = array_filter($classMetadata->getAssociationMappings(), 
                fn($mapping) => $associationManager->isMappingToOne($mapping) && isset($mapping['isOwningSide']) && $mapping['isOwningSide']);
            foreach($filteredAssociations as $field => $_){
                $entityArray[$field] = null;
            }
        }else{
            $entityArray = $entityManipulator->getEntity($fqcn, $id);
        }

        return $this->render('entity/edit.html.twig', [
            'fqcn' => $fqcn,
            'entity' => $entityPrinter->printableEntityEditArray($entityArray, $fqcn),
            'id' => $id,
            'identifier' => $classMetadata->getIdentifier()[0],
        ]);
    }

    #[Route('/entity/d/{fqcn}/{id}', name: 'autoadmin_entity_delete', requirements: ['fqcn' => '.+'])]
    public function delete(string $fqcn, mixed $id, EntityManipulator $entityManipulator, Request $request): Response
    {
        $fqcn = urldecode($fqcn);
        if ($request->isMethod('POST')) {
            $entity = $this->em->find($fqcn, $id);
            if (!$entity) {
                throw $this->createNotFoundException();
            }
            $this->em->remove($entity);
            $this->em->flush();
            return $this->redirectToRoute('autoadmin_entity_list', ['fqcn' => $fqcn]);
        } else{
            return $this->render('entity/confirmDelete.html.twig', [
                'fqcn' => $fqcn,
                'id' => $id,
            ]);
        }

    }
}