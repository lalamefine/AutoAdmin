<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\Proxy;

class EntityManipulator
{
    public function __construct(private EntityManagerInterface $entityManager)
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
        if ($mapping->isManyToMany()) {
            $fieldValue = $classMetadata->getFieldValue($originEntity, $field);
            $collection = $fieldValue->toArray();
        } else if ($mapping->isOneToMany() && isset($mapping['mappedBy'])) {
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
        $mappings = array_filter($classMetadata->getAssociationMappings(), fn($mapping) => $fetchCollections || !$mapping->isToMany());
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

    public function updateEntityFromArray(object $entity, array $data){
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        if(!$metadata) {
            throw new \InvalidArgumentException("Invalid entity class");
        }
        if(isset($data[$metadata->getIdentifier()[0] ?? 'id'])){
            unset($data[$metadata->getIdentifier()[0] ?? 'id']);
        }
        // dd(array_map(fn($field) => $metadata->getFieldMapping($field)['type'] ?? null, $metadata->getFieldNames()));
        foreach ($data as $field => $value) {
            if ($metadata->hasField($field)) {
                if(in_array($metadata->getFieldMapping($field)['type'], ['simple_array', 'json', 'array']) && is_string($value)){
                    $value = json_decode($value, true);
                }
                $metadata->setFieldValue($entity, $field, $value);
            } else if ($metadata->hasAssociation($field) && $metadata->getAssociationMapping($field)->isToOneOwningSide()) {
                if($value === null){
                    $metadata->setFieldValue($entity, $field, null);
                }else{
                    $targetEntityFqcn = $metadata->getAssociationMapping($field)['targetEntity'];
                    $targetEntity = $this->entityManager->getRepository($targetEntityFqcn)->find($value);
                    $metadata->setFieldValue($entity, $field, $targetEntity);
                }
            }
        }   
        $this->entityManager->persist($entity);
        return $entity;
    }
    
    public function getEntityId($entity): ?int
    {
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
        return null;
    }

}
