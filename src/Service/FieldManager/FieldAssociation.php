<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldAssociation extends RelationField
{
    
    public function valueOrNull(int $maxLength): string
    {
        if (is_null($this->value)) {
            return '<i>null</i>';
        }
        $mapping = $this->meta->getAssociationMapping($this->field);
        $associationFqcn = $mapping['targetEntity'];
        $associationClassMetadata = $this->em->getClassMetadata($associationFqcn);
        $associationPKField = $associationClassMetadata->getIdentifierFieldNames()[0] ?? null;
        $associationPK = $associationClassMetadata->getFieldValue($this->value, $associationPKField);
        $label = $this->entityIdentifier->makeTextIdentifierFromClassAndId($associationFqcn, $associationPK);
        if (strlen($label) > $maxLength) {
            $label = substr($label, 0, $maxLength).'...';
        }
        return $this->twig->render('component/entityReference.html.twig', [
            'fqcn' => $associationFqcn,
            'id' => $associationPK,
            'label' => $label
        ]);
    }

    public function inputOrNull(): string
    {
        $mapping = $this->meta->getAssociationMapping($this->field);
        $associationFqcn = $mapping['targetEntity'];
        $associationClassMetadata = $this->em->getClassMetadata($associationFqcn);
        if (count($associationClassMetadata->getIdentifierColumnNames()) == 0) {
            return '<span>PK not found on entity '.$associationFqcn.'</span>';
        }
        if (count($associationClassMetadata->getIdentifierColumnNames()) > 1) {
            return '<span>Composite PK not supported on entity '.$associationFqcn.'</span>';
        }
        $associationPK = $associationClassMetadata->getIdentifierColumnNames()[0];
        $pkValue = $associationClassMetadata->getFieldValue($this->value, $associationPK);
        if(!isset($mapping['isOwningSide']) || !$mapping['isOwningSide']){
            return '<div class="flex flex-row gap-2">
                <span class="text-gray-500 text-nowrap">Referenced by '.$associationFqcn.': </span>'.
                htmlspecialchars((string)$pkValue)
            . '</div>';
        } else {
            if ($mapping['joinColumns'][0]['nullable'] ?? false) {
                $nullableCheckbox = '<input type="checkbox" id="'.$this->field.'_null" name="'.$this->field.'_null" value="1" '.(is_null($this->value) ? 'checked' : '').'/> <label for="'.$this->field.'_null"><i>null</i></label><br/>';
            }else{
                $nullableCheckbox = '';
            }
            return '<div class="flex flex-row gap-2">
                <span class="text-gray-500 text-nowrap">Reference to '.$associationFqcn.' : </span>
                <input type="text" placeholder="'.$associationPK.'" name="'.htmlspecialchars($this->field).'" value="'.htmlspecialchars((string)$pkValue).'" 
                    class="w-full border border-gray-300 rounded px-1 py-0.5"
                    onchange="'.$this->field.'_null.checked = false"
                />
                '.$nullableCheckbox.'
            </div>';
        }
    }
}