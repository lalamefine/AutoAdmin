<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldArray extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['array', 'simple_array', 'json', 'jsonb'];
    }

    public function valueOrNull(int $maxLength): string
    {
        $v = $this->printValue();
        if (strlen($v) > $maxLength) {
            return "<span class=\"text-nowrap\" title=\"".$v."\">".substr($v, 0, $maxLength).'...</span>';
        } else {
            return $v;
        }
    }

    public function printValue(): string
    {
        try {
            $tv = json_encode($this->value, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            $tv = $th->getMessage();
        }
        return htmlspecialchars($tv);
    }

    public function printInput(): string
    {
        $onChange = $this->isNullable() ? " onchange=\"{$this->field}_null.checked=false;\" " : '';
        return '<textarea name="'.$this->field.'" class="w-full border border-gray-300 rounded px-1 py-0.5" '.$onChange.'>'.htmlspecialchars($this->printValue()).'</textarea>';
    }
}