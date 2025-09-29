<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityManagerClosed;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;

class EntityManipulator
{
    public function __construct(private EntityManagerInterface $entityManager, private ManagerRegistry $doctrine, private AssociationManager $associationManager)
    {
    }

    public function getClassMetadata($fqcn)
    {
        $sfqcn = $this->entityManager->getRepository($fqcn)->getClassName();
        return $this->entityManager->getClassMetadata($sfqcn);
    }

    public function getCollection(object $originEntity, string $field): Collection
    {
        $classMetadata = $this->entityManager->getClassMetadata(get_class($originEntity));
        $mapping = $classMetadata->getAssociationMapping($field);
        // Load if proxy
        if ($originEntity instanceof Proxy) {
            $originEntity->__load();
        }

        if (
            $this->associationManager->isFieldToMany(get_class($originEntity), $field)
        ) {
            return $classMetadata->getFieldValue($originEntity, $field);
        } else {
            throw new \Exception("Cas non géré pour le champ $field de l'entité ".get_class($originEntity));
        }
    }

    public function getEntity($fqcn, $id, $fetchCollections = true): ?object
    {
        $classMetadata = $this->entityManager->getClassMetadata($fqcn);
        $identifierField = $classMetadata->getIdentifier()[0];
        if(!$identifierField){
            throw new \InvalidArgumentException("Entity $fqcn has no identifier field");
        }
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from($fqcn, 'e')
            ->where("e.$identifierField = :id")
            ->setParameter('id', $id);

        $i = 0;
        $mappings = $fetchCollections ? $classMetadata->getAssociationMappings() : $this->associationManager->getMappingToOneForClass($fqcn);
        foreach($mappings as $field => $_){
            $letters = substr($field, 0, 3);
            $qb->leftJoin("e.$field", "{$letters}_{$i}");
            $qb->addSelect("{$letters}_{$i}");
            $i++;
        }
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function listEntities()
    {
        return $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
    }

    public function updateEntityFromArray(object $entity, array $data) {
        $metadata = $this->entityManager->getClassMetadata(get_class($entity));
        if(!$metadata) {
            throw new \InvalidArgumentException("Invalid entity class");
        }
        $suposedId = $data[$metadata->getIdentifier()[0]] ?? null;
        if($suposedId === ''){
            $suposedId = null;
        }
        if(isset($data[$metadata->getIdentifier()[0]])){
            if($suposedId !== null){
                $existingEntity = $this->entityManager->getRepository(get_class($entity))->find($suposedId);
            } else{
                $existingEntity = null;
            }
            if($existingEntity || $suposedId === null){ // Do not allow ID updates on an existing entity
                unset($data[$metadata->getIdentifier()[0] ?? 'id']);
            }
        }
        $this->entityManager->persist($entity);
        try {
            foreach ($data as $field => $value) {
                if ($metadata->hasField($field)) {
                    if(in_array($metadata->getFieldMapping($field)['type'], ['simple_array', 'json', 'array']) && is_string($value)){
                        $value = json_decode($value, true);
                    }
                    $metadata->setFieldValue($entity, $field, $value);
                } else 
                    if ($metadata->hasAssociation($field) && in_array($metadata->getAssociationMapping($field)['type'], [
                        ClassMetadata::ONE_TO_ONE, ClassMetadata::MANY_TO_ONE
                        ]) && isset($metadata->getAssociationMapping($field)['isOwningSide']) && $metadata->getAssociationMapping($field)['isOwningSide']) {
                            if($value === null){
                                $metadata->setFieldValue($entity, $field, null);
                            }else{
                                $targetEntityFqcn = $metadata->getAssociationMapping($field)['targetEntity'];
                                $targetEntity = $this->entityManager->getRepository($targetEntityFqcn)->find($value);
                                $metadata->setFieldValue($entity, $field, $targetEntity);
                            }
                }
            }
            $this->entityManager->flush();
        } catch (EntityManagerClosed $e) {
            // Ignore EntityManagerClosed exceptions (flush will close it on first error)
        } catch (\Throwable $th) {
            throw $th;
        }
    }

}
