<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

class EntityPrinter
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouterInterface $router, private EntityIdentifier $entityIdentifier,
        private AssociationManager $associationManager, private FieldPrinterFactory $fieldPrinterFactory,
        private TwigBundleService $twigService
    ) {  }

    public function printEntityRow(object $entity, array $headers, int $maxLength): string
    {   
        if($entity instanceof Proxy || $entity instanceof LazyObjectInterface) {
            $fqcn = get_parent_class($entity);
        } else {
            $fqcn = get_class($entity);
        }
        $entityId = $this->entityIdentifier->getEntityId($entity);
        return $this->twigService->getEnv()->render('entity/row.html.twig', [
            'entityRow' => $entity,
            'fqcn' => $fqcn,
            'headers' => $headers,
            'entityId' => $entityId,
            'printableEntityAr' => $this->printableEntityAr($entity, false, $maxLength)
        ]);
    }

    public function printableEntityAr(object $entity, bool $allowCollections, int $maxLength): array
    {
        $printable = [];
        $classMetadata = $this->em->getClassMetadata(get_class($entity));
        foreach ($classMetadata->getFieldNames() as $field) {
            $printable[$field] = $this->fieldPrinterFactory->getPrinter($entity, $field)->valueOrNull($maxLength);
        }
        foreach ($classMetadata->getAssociationMappings() as $field => $mapping) {
            if ($allowCollections || !$this->associationManager->isMappingToMany($mapping)) {
                $printable[$field] = $this->fieldPrinterFactory->getPrinter($entity, $field)->valueOrNull($maxLength);
            }
        }
        return $printable;
    }

    public function printableEntityEditArray(object $entity, string $fqcn): array
    {
        $printable = [];
        $metadata = $this->em->getClassMetadata($fqcn);
        foreach ($metadata->getFieldNames() as $field) {
            $printable[$field] = $this->fieldPrinterFactory->getPrinter($entity, $field)->inputOrNull();
        }
        foreach ($metadata->getAssociationMappings() as $field => $mapping) {
            $printable[$field] = $this->fieldPrinterFactory->getPrinter($entity, $field)->inputOrNull();
        }

        return $printable;
    }  
}