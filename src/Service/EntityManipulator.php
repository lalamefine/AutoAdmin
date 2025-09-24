<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;

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

    public function arrayToIdLabelMap($arrayOrCollection, $identifierField = 'id'): array
    {
        $collection = is_array($arrayOrCollection) ? $arrayOrCollection : $arrayOrCollection->toArray();
        return array_map(function($e) use ($identifierField) {
            $id = null; $name = '';
            $ref = new \ReflectionObject($e);
            if ($ref->hasProperty($identifierField)) {
                $prop = $ref->getProperty($identifierField);
                $prop->setAccessible(true);
                $id = $prop->getValue($e);
            }
            if(method_exists($e, '__toString')){
                $name = $ref->getShortName(). '#'.$id.' ('. (string)$e .')';
            } else if(method_exists($e, 'getName')){
                $name = $ref->getShortName(). '#'.$id.' ('.$e->getName().')';
            } else if(method_exists($e, 'getTitle')){
                $name = $ref->getShortName(). '#'.$id.' ('.$e->getTitle().')';
            } else {
                $name = $ref->getShortName().'#'.$id;
            }
            return [
                'id' => $id,
                'name' => $name
            ];
        }, $collection);
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
        foreach ($data as $field => $value) {
            if ($metadata->hasField($field)) {
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

    // public function updateEntityFromArray(string $fqcn, $id, array $data): ?object
    // {
    //     $entity = $this->entityManager->getRepository($fqcn)->find($id);
    //     if (!$entity) {
    //         return null;
    //     }
    //     $identifierField = $this->entityManager->getClassMetadata($fqcn)->getIdentifier()[0] ?? 'id';
    //     $classMetadata = $this->entityManager->getClassMetadata($fqcn);
    //     $targetColumns = [...$classMetadata->getFieldNames(), ...array_keys($classMetadata->getAssociationMappings())];
    //     $qb = $this->entityManager->createQueryBuilder()
    //         ->update( $fqcn, 'e')
    //         ->where("e.$identifierField = :id")
    //         ->setParameter('id', $id);
    //     foreach($data as $field => $value){
    //         if ($value === '') {
    //             $value = null;
    //         }
    //         if(!$classMetadata->hasAssociation($field) || $classMetadata->getAssociationMapping($field)->isOwningSide()){
    //             if(in_array($field, $targetColumns) && $field !== 'id'){
    //                 $qb->set('e.'.$field, ':'.$field);
    //                 $qb->setParameter($field, $value);
    //             }
    //         }
    //     }
    //     // dd($qb->getDQL(), $qb->getParameters());
    //     $qb->getQuery()->execute();
    //     return $entity;
    // }

    public function getEntityId($entity): ?int
    {
        $ref = new \ReflectionObject($entity);
        $identifierField = $this->entityManager->getClassMetadata(get_class($entity))->getIdentifier()[0] ?? 'id';
        if ($ref->hasProperty($identifierField)) {
            $prop = $ref->getProperty($identifierField);
            $prop->setAccessible(true);
            return $prop->getValue($entity);
        }
        return null;
    }

}
