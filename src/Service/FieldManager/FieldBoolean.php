<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldBoolean extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['boolean', 'bool'];
    }

    public function printValue(): string
    {
        $value = $this->value;
        return $value ? '<span class="text-green-800">⬤ true</span>' : '<span class="text-red-800">⬤ false</span>';
    }

    public function printInput(): string
    {
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        $out = '
            <input type="radio" id="'.$this->field.'_1" name="'.$this->field.'" value="1" '.($this->value ? 'checked ' : '').$onChange.'/> 
            <label for="'.$this->field.'_1">True</label>
            <input type="radio" id="'.$this->field.'_0" name="'.$this->field.'" value="0" '.(!$this->value ? 'checked ' : '').$onChange.'/> 
            <label for="'.$this->field.'_0">False</label>
        ';
        return $out;
    }
}