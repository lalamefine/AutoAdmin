<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldInteger extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['integer', 'smallint', 'bigint'];
    }

    protected function printValue(): string
    {
        return (string)$this->value;
    }

    public function printInput(): string
    {
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        $out = '<input type="number" step="1" name="'.$this->field.'" value="'.(is_null($this->value) ? '' : $this->value).'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
        if ($this->isNullable()) {
            $out .= '<input type="checkbox" id="'.$this->field.'_null" name="'.$this->field.'_null" value="1" '.(is_null($this->value) ? 'checked' : '').'/> <label for="'.$this->field.'_null"><i>null</i></label><br/>';
        }
        return $out;
    }
}