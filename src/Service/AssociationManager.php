<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;

class AssociationManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function isMappingToMany(AssociationMapping $association): bool
    {
        return in_array($association['type'], [ClassMetadata::ONE_TO_MANY, ClassMetadata::MANY_TO_MANY]);
    }

    public function isMappingToOne(AssociationMapping $association): bool
    {
        return in_array($association['type'], [ClassMetadata::MANY_TO_ONE, ClassMetadata::ONE_TO_ONE]);
    }

    public function isFieldToOne(string $fqcn, string $field): bool
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        return $this->isMappingToOne($classMetadata->getAssociationMapping($field));
    }

    public function isFieldToMany(string $fqcn, string $field): bool
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        return $this->isMappingToMany($classMetadata->getAssociationMapping($field));
    }

    public function getMappingToOneForClass(string $fqcn): array
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        return array_filter(
            $classMetadata->getAssociationMappings(),
            fn($mapping) => $this->isMappingToOne($mapping)
        );
    }

    public function getMappingToManyForClass(string $fqcn): array
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        return array_filter(
            $classMetadata->getAssociationMappings(),
            fn($mapping) => $this->isMappingToMany($mapping)
        );
    }
}