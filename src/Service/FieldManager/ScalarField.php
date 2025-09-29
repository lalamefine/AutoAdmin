<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

use Doctrine\ORM\Mapping\ClassMetadata;

abstract class ScalarField implements FieldPrinterInterface
{
    protected ClassMetadata $meta;
    protected string $field;
    protected mixed $value;
    
    public function __construct(ClassMetadata $meta, string $field, mixed $value = null) {
        $this->meta = $meta;
        $this->field = $field;
        $this->value = $value;
    }

    protected static abstract function acceptedTypes(): array;
    protected abstract function printValue(): string; // Use valueOrNull
    protected abstract function printInput(): string; // Use inputOrNull

    public function valueOrNull(int $maxLength): string
    {
        if (is_null($this->value)) {
            return '<i>null</i>';
        }
        $v = $this->printValue();
        if (strlen($v) > $maxLength) {
            return substr($v, 0, $maxLength).'...';
        }
        return $v;
    }

    public function inputOrNull(): string
    {
        $nullInput = $this->isNullable() ? '
            <input type="checkbox" id="'.$this->field.'_null" name="'.$this->field.'_null" value="1" '.(is_null($this->value) ? 'checked' : '').'/>
            <label for="'.$this->field.'_null"><i>null</i></label><br/>
            ' : '';
        return $this->printInput().$nullInput;
    }

    public static function acceptsType(string $type): bool
    {
        return in_array($type, static::acceptedTypes());
    }

    protected function isNullable(): bool
    {
        return $this->meta->getFieldMapping($this->field)['nullable'] ?? false;
    }

}