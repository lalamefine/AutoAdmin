<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldTime extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['time'];
    }

    protected function printValue(): string
    {
        return $this->value instanceof \DateTimeInterface ? $this->value->format('H:i:s') : '';
    }

    public function printInput(): string
    {
        $v = $this->value instanceof \DateTimeInterface ? $this->value->format('H:i:s') : '';
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        $out = '<input type="time" name="'.$this->field.'" value="'.$v.'" '.$onChange.' class="w-full border border-gray-300 rounded px-1 py-0.5"/>';
        if ($this->isNullable()) {
            $out .= '<input type="checkbox" id="'.$this->field.'_null" name="'.$this->field.'_null" value="1" '.(is_null($this->value) ? 'checked' : '').'/> <label for="'.$this->field.'_null"><i>null</i></label><br/>';
        }
        return $out;
    }
}