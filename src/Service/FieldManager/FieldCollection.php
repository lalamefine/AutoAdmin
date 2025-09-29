<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

use Doctrine\ORM\Mapping\ClassMetadata;

class FieldCollection extends RelationField
{
    
    
    public function valueOrNull(int $maxLength): string
    {
        return $this->printCollectionLoader($this->entityId, false);
    }
    public function inputOrNull(): string
    {
        return $this->printCollectionLoader($this->entityId, true);
    }

    private function printCollectionLoader($sourceId, $modeEdition = false): string
    {
        // Checks
        if (!isset($this->meta->getAssociationMappings()[$this->field])) {
            return "Not an association field";
        }
        $mapping = $this->meta->getAssociationMapping($this->field);
        if ($modeEdition && $this->associationManager->isMappingToOne($mapping)) {
            return "Not a to-many association";
        }
        $fqcn = $this->meta->getName();
        // Count elements
        $elementsCount = 0;
        if ($mapping['type'] == ClassMetadata::MANY_TO_MANY) {
            $entity = $this->em->find($fqcn, $sourceId);
            $elementsCount = $this->meta->getFieldValue($entity, $this->field)->count() ?? 0;
        } else
        if( $mapping['type'] == ClassMetadata::ONE_TO_MANY && isset($mapping['mappedBy'])) {
            $elementsCount = $this->em->getRepository($mapping['targetEntity'])->count([$mapping['mappedBy'] ?? $mapping['inversedBy'] => $sourceId]);
        }
        // Render
        return $this->twig->render('component/collectionLoader.html.twig', [
            'sourceId' => $sourceId,
            'type' => match($mapping['type'] ?? null) {
                ClassMetadata::MANY_TO_MANY => '*to1',
                ClassMetadata::ONE_TO_MANY => '1to*',
                ClassMetadata::MANY_TO_ONE => '*to1',
                ClassMetadata::ONE_TO_ONE => '1to1',
                default => '?'
            },
            'field' => $this->field,
            'elementCount' => $elementsCount,
            'fqcn' => $fqcn,
            'modeEdition' => $modeEdition
        ]);
    }
}