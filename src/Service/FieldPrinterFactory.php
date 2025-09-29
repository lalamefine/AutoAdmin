<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Lalamefine\Autoadmin\LalamefineAutoadminBundle;
use Lalamefine\Autoadmin\Service\FieldManager\Field;
use Lalamefine\Autoadmin\Service\FieldManager\FieldPrinterInterface;
use Lalamefine\Autoadmin\Service\FieldManager\FixedTextPrinter;
use Lalamefine\Autoadmin\Service\FieldManager\ScalarField;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FieldPrinterFactory
{
    protected static array $fieldPrinters = [
        FieldManager\FieldString::class,
        FieldManager\FieldText::class,
        FieldManager\FieldArray::class,
        FieldManager\FieldInteger::class,
        FieldManager\FieldFloat::class,
        FieldManager\FieldBoolean::class,
        FieldManager\FieldDate::class,
        FieldManager\FieldTime::class,
        FieldManager\FieldDateTime::class,
        FieldManager\FieldDateTimeZ::class,
        FieldManager\FieldBinary::class,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private AssociationManager $associationManager,
        private EntityIdentifier $entityIdentifier,
        private LalamefineAutoadminBundle $bundle,
        private TwigBundleService $twigService
    ) { }

    public function getPrinter(object $entity, string $field): ?FieldPrinterInterface
    {
        $meta = $this->em->getClassMetadata(get_class($entity));
        $entityId = $this->entityIdentifier->getEntityId($entity) ?? null;
        $value = $meta->getFieldValue($entity, $field);
        // Relations
        if ($meta->hasAssociation($field)) {
            $associationMapping = $meta->getAssociationMapping($field);
            if ($this->associationManager->isMappingToMany($associationMapping)) {
                if (!$entityId) {
                    return new FixedTextPrinter(
                        "<i class=\"text-gray-500\">(save entity to manage collection)</i>"
                    );
                }
                return new FieldManager\FieldCollection($meta, $entityId, $field, $value, $this->em, $this->twigService->getEnv());
            } elseif ($this->associationManager->isMappingToOne($associationMapping)) {
                return new FieldManager\FieldAssociation($meta, $entityId, $field, $value, $this->em, $this->twigService->getEnv());
            } else {
                return new FixedTextPrinter("<i>Association type not supported : {$associationMapping['type']}</i>");
            }
        // Others
        } else {
            $mapping = $meta->getFieldMapping($field);
            /** @var ScalarField $printerClass */
            foreach (static::$fieldPrinters as $printerClass) {
                if ($printerClass::acceptsType($mapping['type'])) {
                    return new $printerClass($meta, $field, $value);
                }
            }
        }
        return new FixedTextPrinter("<i>Type not supported: {$mapping['type']}</i>");
    }

}
