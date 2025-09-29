<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldDateTime extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['datetime', 'timestamp', 'datetime_immutable' ];
    }

    protected function printValue(): string
    {
        return $this->value instanceof \DateTimeInterface ? $this->value->format('Y-m-d H:i:s') : '';
    }

    public function printInput(): string
    {
        $v = $this->value instanceof \DateTimeInterface ? $this->value->format('Y-m-d H:i:s') : '';
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        return '<input type="datetime" name="'.$this->field.'" value="'.$v.'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
    }
}