<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldText extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['text'];
    }

    public function valueOrNull(int $maxLength): string
    {
        $v = $this->printValue();
        if (strlen($v) > $maxLength) {
            return "<span class=\"text-nowrap\" title=\"".htmlspecialchars($v)."\">".htmlspecialchars(substr($v, 0, $maxLength)).'...</span>';
        } else {
            return htmlspecialchars($v);
        }
    }
    
    public function printValue(): string
    {
        return htmlspecialchars((string)$this->value);
    }

    public function printInput(): string
    {
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        $out = '<textarea name="'.$this->field.'" class="w-full border border-gray-300 rounded px-1 py-0.5" '.$onChange.' style="field-sizing: content;">'.htmlspecialchars((string)$this->value).'</textarea>';
        if ($this->isNullable()) {
            $out .= '<br/><input type="checkbox" id="'.$this->field.'_null" name="'.$this->field.'_null" value="1" '.(is_null($this->value) ? 'checked' : '').'/> <label for="'.$this->field.'_null"><i>null</i></label><br/>';
        }
        return $out;
    }
}