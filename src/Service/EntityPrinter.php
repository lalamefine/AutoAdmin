<?php namespace Lalamefine\Autoadmin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Proxy;
use Lalamefine\Autoadmin\LalamefineAutoadminBundle;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class EntityPrinter
{
    private Environment $twig;

    public function __construct(
        private EntityManagerInterface $em, private LalamefineAutoadminBundle $bundle, 
        private RouterInterface $router, private EntityIdentifier $entityIdentifier,
        private AssociationManager $associationManager
    ) {
        $loader = new FilesystemLoader($bundle->getPath().'/src/templates/');
        $this->twig = new Environment($loader);
        $this->twig->addFunction(new \Twig\TwigFunction('path', function ($route, $params = []) {
            return $this->router->generate($route, $params);
            return $router->generate($route, $params);
        }));
    }

    public function printEntityRow(array $entityRow, string $fqcn, array $headers, int $maxLength): string
    {   
        return $this->twig->render('entity/row.html.twig', [
            'entityRow' => $entityRow,
            'fqcn' => $fqcn,
            'headers' => $headers,
            'classMetadata' => $this->em->getClassMetadata($fqcn),
            'printableEntityAr' => $this->printableEntityAr($entityRow, $fqcn, false, $maxLength)
        ]);
    }

    public function printableEntityAr(array $entityRow, string $fqcn, bool $allowCollections, int $maxLength): array
    {
        $printable = [];
        $classMetadata = $this->em->getClassMetadata($fqcn);
        if (count($classMetadata->getIdentifier()) != 1) {
            throw new \Exception("Entity $fqcn needs to have exactly one identifier field, current: ".implode(', ', $classMetadata->getIdentifier()).".");
        }
        $idField = $classMetadata->getIdentifier()[0];
        if (!isset($entityRow[$idField]) || $entityRow[$idField] === null) {
            throw new \Exception("Entity id field '$idField' is missing or null in entity row for $fqcn.");
        }
        $entityId = $entityRow[$idField];
        foreach ($classMetadata->getFieldNames() as $field) {
            if(array_key_exists($field, $entityRow)){
                $printable[$field] = $this->printValue($entityRow[$field], $field, $fqcn, $maxLength);
            }else{
                $printable[$field] = 'MISSING DATA';
            }
        }
        foreach ($classMetadata->getAssociationMappings() as $field => $associationMapping) {
            $value = $entityRow[$field] ?? null;
            if($allowCollections && $this->associationManager->isMappingToMany($associationMapping)){
                $printable[$field] = $this->printCollectionLoader($entityId, $field, $fqcn, false);
            }else{
                $printable[$field] = $this->printValue($value, $field, $fqcn, $maxLength);
            }
        }
        return $printable;
    }

    public function printValue($fieldValue, $field, $fqcn, $maxLength): string
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        $associations = array_keys($classMetadata->getAssociationMappings());
        if (count($classMetadata->getIdentifier()) != 1) {
            throw new \Exception("Entity $fqcn needs to have exactly one identifier field, current: ".implode(', ', $classMetadata->getIdentifier()).".");
        }
        $identifierField = $classMetadata->getIdentifier()[0];
        if (in_array($field, $associations)) {
            $associationFqcn = $classMetadata->getAssociationTargetClass($field);
            $associationPK = $this->em->getClassMetadata($associationFqcn)->getIdentifierColumnNames()[0] ?? null;
            if ($fieldValue === null) {
                return "<i class=\"text-gray-500\">null</i>";
            } else {
                return $this->linkToEntity($associationFqcn, $fieldValue[$associationPK]);
            }
        } else if ($field === $identifierField) {
            if ($fieldValue === null) {
                return "<i class=\"text-red-500\">KO</i>";
            }
            $rurl = $this->router->generate('autoadmin_entity_view', ['fqcn' => $fqcn, 'id' => $fieldValue]);
            return "<a class=\"hover:text-blue-800 bold bg-blue-100 hover:bg-blue-200 hover:border-blue-300 px-2 hover:cursor-pointer border border-transparent rounded-xl\"
                hx-get=\"$rurl\" href=\"$rurl\"
                hx-push-url=\"true\"
                hx-target=\"#content\" hx-swap=\"innerHTML\">".$fieldValue."</a>";
        } else if (is_bool($fieldValue)) {
            return $fieldValue ? '<span class="text-green-800">⬤ true</span>' : '<span class="text-red-800">⬤ false</span>';
        } else if (is_array($fieldValue)) {
            try {
                $tv = json_encode($fieldValue, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $th) {
                $tv = $th->getMessage();
            }
            if (strlen($tv) > $maxLength) {
                return "<span class=\"text-nowrap\" title=\"".htmlspecialchars($tv)."\">".htmlspecialchars(substr($tv, 0, $maxLength)).'...</span>';
            } else {
                return htmlspecialchars($tv);
            }
        } else if (is_null($fieldValue)) {
            return '<i class="text-gray-500">null</i>';
        } else if ($fieldValue instanceof \DateTimeInterface) {
            return $fieldValue->format('Y-m-d H:i:s');
        } else if ((string)$fieldValue??'' !== '') {
            if (strlen((string)$fieldValue) > $maxLength) {
                return "<span title=\"".htmlspecialchars((string)$fieldValue)."\">".htmlspecialchars(substr((string)$fieldValue, 0, $maxLength)).'...</span>';
            } else {
                return htmlspecialchars((string)$fieldValue);
            }
        }
        return '';
    }

    public function printableEntityEditArray(array $entity, string $fqcn): array
    {
        $printable = [];
        $metadata = $this->em->getClassMetadata($fqcn);
        $entityId = $this->entityIdentifier->getEntityId($entity) ?? null;
        foreach ($metadata->getFieldNames() as $field) {
            $printable[$field] = $this->printValueEditInput($entity[$field] ?? null, $field, $fqcn);
        }
        foreach ($metadata->getAssociationMappings() as $field => $mapping) {
            if ($this->associationManager->isMappingToMany($mapping)) {
                if (!$entityId) {
                    $printable[$field] = "<i class=\"text-gray-500\">(save entity to manage collection)</i>";
                    continue;
                }
                $printable[$field] = $this->printCollectionLoader($entityId, $field, $fqcn, true);
            } else{
                $printable[$field] = $this->printAssociationEditInput($entity[$field] ?? null, $field, $fqcn);
            }
        }

        return $printable;
    }

    public function printValueEditInput($fieldValue, $field, $fqcn): string
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        $associations = array_keys($classMetadata->getAssociationMappings());
        $identifierField = $classMetadata->getIdentifier()[0] ?? null;
        $fieldType = $this->em->getClassMetadata($fqcn)->getTypeOfField($field);
        $out = '<div class="flex flex-row gap-2 items-center mb-1">';
        $esField = htmlspecialchars($field);
        if (in_array($field, $associations)) {
            return "Association editing not supported";
        } else if ($field === $identifierField) {
            $disabled = ($fieldValue !== null) ? 'disabled readonly' : 'name="'.$esField.'"';
            return '<input '.$disabled.' type="text" value="'.htmlspecialchars((string)$fieldValue).'"  class="w-full border border-gray-300 rounded px-1 py-0.5 '.($fieldValue !== null ? 'bg-gray-100' : '').'"/> ';
        }
        if ($classMetadata->isNullable($field)) {
            $onChange = " onchange=\"{$esField}_null.checked=false;\" ";
        } else {
            $onChange = ' ';
        }
        switch ($fieldType) {
            case 'boolean':
                $out .= '
                    <input type="radio" id="'.$esField.'_1" name="'.$esField.'" value="1" '.($fieldValue ? 'checked ' : '').$onChange.'/> <label for="'.$esField.'_1">True</label>
                    <input type="radio" id="'.$esField.'_0" name="'.$esField.'" value="0" '.(!$fieldValue ? 'checked ' : '').$onChange.'/> <label for="'.$esField.'_0">False</label>
                ';
                break;
            case 'date':
            case 'datetime':
            case 'datetime_immutable':
                $v = $fieldValue instanceof \DateTimeInterface ? $fieldValue->format('Y-m-d') : '';
                $out .= '<input type="date" name="'.$esField.'" value="'.$v.'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
                break;
            case 'datetimetz':
            case 'datetimetz_immutable':
                $v = $fieldValue instanceof \DateTimeInterface ? $fieldValue->format('Y-m-d\TH:i') : '';
                $out .= '<input type="datetime-local" name="'.$esField.'" value="'.$v.'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
                break;
            case 'integer':
            case 'float':
                $out .= '<input type="number" name="'.$esField.'" value="'.htmlspecialchars((string)$fieldValue).'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
                break;
            case 'string':
                $out .= '<input type="text" name="'.$esField.'" value="'.htmlspecialchars((string)$fieldValue).'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
                break;
            case 'text':
                $out .= '<textarea name="'.$esField.'" class="w-full border border-gray-300 rounded px-1 py-0.5" '.$onChange.' style="field-sizing: content;">'.htmlspecialchars($fieldValue).'</textarea>';
                break;
            case 'json':
                $out .= '<textarea name="'.$esField.'" class="w-full border border-gray-300 rounded px-1 py-0.5" '.$onChange.' style="field-sizing: content;">'.htmlspecialchars(json_encode($fieldValue, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).'</textarea>';
                break;
            case 'simple_array':
                $out .= '<textarea name="'.$esField.'" class="w-full border border-gray-300 rounded px-1 py-0.5" '.$onChange.' style="field-sizing: content;">'.htmlspecialchars(json_encode($fieldValue, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).'</textarea>';
                break;
            default:
                $out = "Type non géré: ".htmlspecialchars($fieldType);
                break;
        }
        if ($classMetadata->isNullable($field)) {
            $out .= '<input type="checkbox" id="'.$esField.'_null" name="'.$esField.'_null" value="1" '.(is_null($fieldValue) ? 'checked' : '').'/> <label for="'.$esField.'_null"><i>null</i></label><br/>';
        }
        return $out.'</div>';
    }

    public function printCollectionLoader($sourceId, $field, $fqcn, $modeEdition = false): string
    {
        // Checks
        $classMetadata = $this->em->getClassMetadata($fqcn);
        if (!isset($classMetadata->getAssociationMappings()[$field])) {
            return "Not an association field";
        }
        $mapping = $classMetadata->getAssociationMapping($field);
        if ($modeEdition && $this->associationManager->isMappingToOne($mapping)) {
            return "Not a to-many association";
        }
        // Count elements
        $elementsCount = 0;
        if ($mapping['type'] == ClassMetadata::MANY_TO_MANY) {
            $entity = $this->em->find($fqcn, $sourceId);
            $elementsCount = $classMetadata->getFieldValue($entity, $field)->count() ?? 0;
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
            'field' => $field,
            'elementCount' => $elementsCount,
            'fqcn' => $fqcn,
            'modeEdition' => $modeEdition
        ]);
    }

    public function printAssociationEditInput($fieldValue, $field, $fqcn): string
    {
        $classMetadata = $this->em->getClassMetadata($fqcn);
        if (!isset($classMetadata->getAssociationMappings()[$field])) {
            return "Not an association field";
        }
        $mapping = $classMetadata->getAssociationMapping($field);
        $associationFqcn = $mapping['targetEntity'];
        $associationClassMetadata = $this->em->getClassMetadata($associationFqcn);
        $associationPK = $associationClassMetadata->getIdentifierColumnNames()[0] ?? null;
        if(!isset($mapping['isOwningSide']) || !$mapping['isOwningSide']){
            return '<div class="flex flex-row gap-2">
                <span class="text-gray-500 text-nowrap">Referenced by '.$associationFqcn.': </span>'.
                htmlspecialchars((string)($fieldValue[$associationPK] ?? ''))
            . '</div>';
        } else {
            if ($mapping['joinColumns'][0]['nullable'] ?? false) {
                $nullableCheckbox = '<input type="checkbox" id="'.$field.'_null" name="'.$field.'_null" value="1" '.(is_null($fieldValue) ? 'checked' : '').'/> <label for="'.$field.'_null"><i>null</i></label><br/>';
            }else{
                $nullableCheckbox = '';
            }
            return '<div class="flex flex-row gap-2">
                <span class="text-gray-500 text-nowrap">Reference to '.$associationFqcn.' : </span>
                <input type="text" placeholder="'.$associationPK.'" name="'.htmlspecialchars($field).'" value="'.htmlspecialchars((string)($fieldValue[$associationPK] ?? '')).'" 
                    class="w-full border border-gray-300 rounded px-1 py-0.5"
                    onchange="'.$field.'_null.checked = false"
                />
                '.$nullableCheckbox.'
            </div>';
        }
    }

    function inColoredDiv($text) {
        return "<div class=\"rounded-xl bg-blue-100 ms-1 px-1\">$text</div>";
    }

    public function linkToEntity(string $fqcn, mixed $id, ?string $label = null): string
    {
        $label = $label ?? $this->entityIdentifier->makeTextIdentifierFromClassAndId($fqcn, $id);
        return $this->twig->render('component/entityReference.html.twig', [
            'fqcn' => $fqcn,
            'id' => $id,
            'label' => $label
        ]);
    }
}