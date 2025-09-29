<?php

namespace Lalamefine\Autoadmin\Service\FieldManager;

interface FieldPrinterInterface
{
    public function valueOrNull(int $maxLength): string;
    public function inputOrNull(): string;
}
