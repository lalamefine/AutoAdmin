<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FieldBinary extends ScalarField
{
    public static function acceptedTypes(): array
    {
        return ['binary', 'blob'];
    }

    protected function printValue(): string
    {
        return '<i>Not implemented</i>';
    }

    public function printInput(): string
    {
        return '<i>Not implemented</i>';
    }
}