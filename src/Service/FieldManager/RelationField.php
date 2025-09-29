<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Lalamefine\Autoadmin\Service\AssociationManager;
use Lalamefine\Autoadmin\Service\EntityIdentifier;
use Twig\Environment;

abstract class RelationField implements FieldPrinterInterface
{
    protected ClassMetadata $meta;
    protected EntityManagerInterface $em;
    protected Environment $twig;
    protected AssociationManager $associationManager;
    protected EntityIdentifier $entityIdentifier;

    protected string $field;
    protected mixed $value;
    protected mixed $entityId;

    public function __construct(
        ClassMetadata $meta, mixed $entityId, string $field, mixed $value = null,
        EntityManagerInterface $em, Environment $twig) {
        $this->meta = $meta;
        $this->field = $field;
        $this->value = $value;
        $this->entityId = $entityId;
        $this->em = $em;
        $this->twig = $twig;
        $this->associationManager = new AssociationManager($em);
        $this->entityIdentifier = new EntityIdentifier($em);
    }

    protected function isNullable(): bool
    {
        return $this->meta->getFieldMapping($this->field)['nullable'] ?? false;
    }
}