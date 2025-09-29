<?php namespace Lalamefine\Autoadmin\Service\FieldManager;

class FixedTextPrinter implements FieldPrinterInterface
{
    protected string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    } 

    public function valueOrNull(int $maxLength): string
    {
        return $this->value;
    }
    
    public function inputOrNull(): string
    {
        return $this->value;
    }
    
}