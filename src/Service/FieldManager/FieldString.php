<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldString extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['string', 'ascii_string', 'guid', 'enum'];
    }

    protected function printValue(): string
    {
        return htmlspecialchars((string)$this->value);
    }

    public function printInput(): string
    {
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        return '<input type="text" name="'.$this->field.'" value="'.htmlspecialchars((string)$this->value).'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
    }
}