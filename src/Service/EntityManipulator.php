<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;

class EntityManipulator
{
    public function __construct(private EntityManagerInterface $entityManager, private ManagerRegistry $doctrine)
    {
    }

    public function getClassMetadata($fqcn)
    {
        $sfqcn = $this->entityManager->getRepository($fqcn)->getClassName();
        return $this->entityManager->getClassMetadata($sfqcn);
    }

    public function getCollection(object $originEntity, string $field): ArrayCollection
    {
        $fqcn = get_class($originEntity);
        $classMetadata = $this->entityManager->getClassMetadata($fqcn);
        $association = $classMetadata->getAssociationMapping($field);
        $fqcnAssociation = $association['targetEntity'];
        $mapping = $this->entityManager->getClassMetadata($fqcn)->getAssociationMapping($field);
        $collection = [];
        if ($mapping['type'] == ClassMetadata::MANY_TO_MANY) {
            $fieldValue = $classMetadata->getFieldValue($originEntity, $field);
            $collection = $fieldValue->toArray();
        } else if ($mapping['type'] == ClassMetadata::ONE_TO_MANY && isset($mapping['mappedBy'])) {
            $targetRepo = $this->entityManager->getRepository($fqcnAssociation);
            $id = $this->getEntityId($originEntity);
            if($id === null){
                throw new \Exception("Impossible de récupérer l'identifiant de l'entité $fqcn");
            }
            $collection = $targetRepo->findBy([$mapping['mappedBy'] => $id]);
        } else {
            throw new \Exception("Cas non géré pour le champ $field de l'entité $fqcn");
        }
        return new ArrayCollection($collection);
    }


    public function getEntityArray($fqcn, $id, $fetchCollections = true): ?array
    {
        $identifierField = $this->entityManager->getClassMetadata($fqcn)->getIdentifier()[0] ?? 'id';
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($fqcn, 'e')
            ->where("e.$identifierField = :id")
            ->setParameter('id', $id);

        $classMetadata = $this->entityManager->getClassMetadata($fqcn);
        $i = 0;
        $mappings = array_filter($classMetadata->getAssociationMappings(), fn($mapping) => $fetchCollections || !in_array($mapping['type'], [ClassMetadata::ONE_TO_MANY, ClassMetadata::MANY_TO_MANY]));
        foreach($mappings as $field => $_){
            $letters = substr($field, 0, 3);
            $qb->leftJoin("e.$field", "{$letters}_{$i}");
            $qb->addSelect("{$letters}_{$i}");
            $i++;
        }                    
        return $qb->getQuery()->getArrayResult()[0] ?? null;
    }

    public function listEntities()
    {
        return $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    }


    public function updateEntityFromArray(object $entity, array $data) : array{
        $this->entityManager->flush();
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        if(!$metadata) {
            throw new \InvalidArgumentException("Invalid entity class");
        }
        if(isset($data[$metadata->getIdentifier()[0] ?? 'id'])){
            $repo = $this->entityManager->getRepository(get_class($entity));
            $existingEntity = $repo->find($data[$metadata->getIdentifier()[0] ?? 'id']);
            if($existingEntity){ // Do not allow ID updates on an existing entity
                unset($data[$metadata->getIdentifier()[0] ?? 'id']);
            }
        }
        // dd(array_map(fn($field) => $metadata->getFieldMapping($field)['type'] ?? null, $metadata->getFieldNames()));
        $this->entityManager->persist($entity);
        $rejections = [];
        foreach ($data as $field => $value) {
            $oldValue = null;
            if ($metadata->hasField($field) || $metadata->hasAssociation($field)) {
                $oldValue = $metadata->getFieldValue($entity, $field);
            }
            try {
                if ($metadata->hasField($field)) {
                    if(in_array($metadata->getFieldMapping($field)['type'], ['simple_array', 'json', 'array']) && is_string($value)){
                        $value = json_decode($value, true);
                    }
                    $metadata->setFieldValue($entity, $field, $value);
                } else if ($metadata->hasAssociation($field) && in_array($metadata->getAssociationMapping($field)['type'], [ClassMetadata::ONE_TO_ONE, ClassMetadata::MANY_TO_ONE]) && isset($metadata->getAssociationMapping($field)['isOwningSide']) && $metadata->getAssociationMapping($field)['isOwningSide']) {
                    if($value === null){
                        $metadata->setFieldValue($entity, $field, null);
                    }else{
                        $targetEntityFqcn = $metadata->getAssociationMapping($field)['targetEntity'];
                        $targetEntity = $this->entityManager->getRepository($targetEntityFqcn)->find($value);
                        $metadata->setFieldValue($entity, $field, $targetEntity);
                    }
                }
                $this->entityManager->flush();
            } catch (EntityManagerClosed $e) {
                // Ignore
            } catch (\Throwable $th) {
                $rejections[$field] = [
                    'reason' => str_replace('An exception occurred while executing a query: ', '', $th->getMessage()),
                    'value' => is_object($value) ? '!object' : (is_array($value) ? '!array' : $value)
                ];
            }
        }
        return $rejections;
    }
    
    public function getEntityId($entity): ?int
    {
        try {
            if ($entity instanceof Proxy) {
                $entity->__load(); // force le chargement de toutes les données
                $entityClass = get_parent_class($entity);
            } else {
                $entityClass = get_class($entity);
            }
            $ref = new \ReflectionClass($entityClass);
            $identifierField = $this->entityManager->getClassMetadata(get_class($entity))->getIdentifier()[0] ?? 'id';
            if ($ref->hasProperty($identifierField)) {
                $prop = $ref->getProperty($identifierField);
                $prop->setAccessible(true);
                return $prop->getValue($entity);
            }
        } catch (\Throwable $th) {
            throw new \Exception("Failed to get entity ID", 1, $th);
        }
        return null;
    }

}
