<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldFloat extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['float', 'double', 'decimal', 'number', 'smallfloat'];
    }

    protected function printValue(): string
    {
        return (string)$this->value;
    }

    public function printInput(): string
    {
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        return '<input type="number" name="'.$this->field.'" value="'.htmlspecialchars($this->printValue()).'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
    }
}