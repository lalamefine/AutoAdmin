<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldDate extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['date'];
    }

    protected function printValue(): string
    {
        return $this->value instanceof \DateTimeInterface ? $this->value->format('Y-m-d') : '';
    }

    public function printInput(): string
    {
        $v = $this->value instanceof \DateTimeInterface ? $this->value->format('Y-m-d') : '';
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        return '<input type="date" name="'.$this->field.'" value="'.$v.'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
    }
}