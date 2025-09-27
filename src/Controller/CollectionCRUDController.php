<?php namespace Lalamefine\Autoadmin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Lalamefine\Autoadmin\Controller\AutoAdminAbstractController;
use Lalamefine\Autoadmin\Service\EntityIdentifier;
use Lalamefine\Autoadmin\Service\EntityManipulator;
use Lalamefine\Autoadmin\Service\EntityPrinter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

class CollectionCRUDController extends AutoAdminAbstractController
{
    #[Route('/collection/r/{fqcn}/{id}/{field}', name: 'autoadmin_collection_view', requirements: ['fqcn' => '.+', 'id' => '.*'])]
    public function viewCollection(string $fqcn, mixed $id, string $field, EntityManipulator $entityManipulator, EntityIdentifier $entityIdentifier): Response
    {
        $fqcn = urldecode($fqcn);
        $origin = $this->em->find($fqcn, $id);
        if (!$origin) {
            throw $this->createNotFoundException("Entity $fqcn with ID $id not found");
        }
        $classMetadata = $this->em->getClassMetadata($fqcn);
        $association = $classMetadata->getAssociationMapping($field);
        $fqcnAssociation = $association['targetEntity'];
        $collection = $entityManipulator->getCollection($origin, $field);
        $reverseIdField = $this->em->getClassMetadata($fqcnAssociation)->getIdentifier()[0] ?? null;
        return $this->render('modals/collection.html.twig', [
            'collection' => $entityIdentifier->arrayToIdLabelMap($collection, $reverseIdField),
            'fqcn' => $fqcnAssociation,
            'originId' => $id,
            'update' => false
        ]);
    }

    #[Route('/collection/u/{fqcn}/{id}/{field}', name: 'autoadmin_collection_update', requirements: ['fqcn' => '.+', 'id' => '.*'])]
    public function updateCollection(string $fqcn, mixed $id, string $field, EntityManipulator $entityManipulator, EntityIdentifier $entityIdentifier, Request $request): Response
    {
        $fqcn = urldecode($fqcn);
        $origin = $this->em->find($fqcn, $id);
        if (!$origin) {
            throw $this->createNotFoundException("Entity $fqcn with ID $id not found");
        }
        $classMetadata = $this->em->getClassMetadata($fqcn);
        $association = $classMetadata->getAssociationMapping($field);
        $fqcnAssociation = $association['targetEntity'];
        $collection = $entityManipulator->getCollection($origin, $field);
        $reverseIdField = $this->em->getClassMetadata($fqcnAssociation)->getIdentifier()[0] ?? null;
        if($request->isMethod('POST')){
            $data = $request->request->all();
            if(isset($data['remove'])){
                foreach($data['remove'] as $toRemove){
                    [$f, $refId] = explode('/', $toRemove);
                    $collection = $collection->filter(fn($e) => $entityIdentifier->getEntityId($e) != $refId);
                }
                unset($data['remove']);
            }
            if(isset($data['add'])){
                foreach($data['add'] as $toAdd){
                    [$f, $refId] = explode('/', $toAdd);
                    $entityToAdd = $this->em->getRepository($fqcnAssociation)->find($refId);
                    if($entityToAdd){
                        $collection->add($entityToAdd);
                    }
                }
                unset($data['add']);
            }
        }

        if(isset($association['isOwningSide']) && $association['isOwningSide'] && $association['type'] != \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_MANY){
            $deletable = $this->em->getClassMetadata($fqcnAssociation)->getAssociationMapping($association['mappedBy'])['joinColumns'][0]['nullable'] ?? false;
        } else {
            $deletable = true;
        }
        return $this->render('modals/collection.html.twig', [
            'collection' => $entityIdentifier->arrayToIdLabelMap($collection, $reverseIdField),
            'fqcn' => $fqcnAssociation,
            'owningFqcn' => $fqcn,
            'field' => $field,
            'originId' => $id,
            'deletable' => $deletable,
            'update' => true
        ]);
    }

    #[Route('/collection/d/{fqcn}/{field}/{refId}', name: 'autoadmin_collection_remove_element', requirements: ['fqcn' => '.+', 'refId' => '.*'], methods: ['POST'])]
    public function removeElement(string $fqcn, string $field, int $refId, EntityIdentifier $entityIdentifier): Response
    {
        $fqcn = urldecode($fqcn);
        $mapping = $this->em->getClassMetadata($fqcn)->getAssociationMappings();
        $fqcnAssociation = $mapping[$field]['targetEntity'] ?? null;
        if(!$fqcnAssociation){
            throw $this->createNotFoundException("Field $field is not a valid association for entity $fqcn");
        }
        return new Response('<div class="hover:line-through hover:cursor-pointer" onclick="this.remove()">
            <input type="hidden" name="remove[]" class="subform-'.$field.'" value="'.$field.'/'.$refId.'" />
            <span class="text-red-600"> &minus; '.$entityIdentifier->makeTextIdentifierFromClassAndId($fqcnAssociation, $refId).'</span>
        </div>');
    }

    #[Route('/collection/a/{fqcn}/{field}/byID', name: 'autoadmin_collection_add_element', requirements: ['fqcn' => '.+'], methods: ['POST'])]
    public function addElement(string $fqcn, string $field, EntityIdentifier $entityIdentifier, Request $request): Response
    {
        $fqcn = urldecode($fqcn);
        $refId = (int) ($request->request->get('item_id') ?? 0);
        $mapping = $this->em->getClassMetadata($fqcn)->getAssociationMappings();
        $fqcnAssociation = $mapping[$field]['targetEntity'] ?? null;
        $refExists = $this->em->getRepository($fqcnAssociation)->find($refId);
        if (!$fqcnAssociation) {
            $resContent = 'Error: Field ' . htmlspecialchars($field) . ' is not a valid association for entity ' . htmlspecialchars($fqcn);
        } else if (!$refId || !$refExists) {
            $resContent = 'Error: Invalid reference ID ' . htmlspecialchars((string)$refId) . ' for entity ' . htmlspecialchars($fqcnAssociation);
        } else {
            try {
                $resContent = '<input type="hidden" name="add[]" class="subform-'.$field.'" value="'.$field.'/'.$refId.'" />
                    <span class="text-green-600"> &plus; '.$entityIdentifier->makeTextIdentifierFromClassAndId($fqcnAssociation, $refId).'</span>';
            } catch (\Throwable $th) {
                $resContent = 'Error: ' . $th->getMessage();
            }
        }
        return new Response('<div class="hover:line-through hover:cursor-pointer" onclick="this.remove()">'.$resContent.'</div>');
    }

}