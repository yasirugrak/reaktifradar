<?php

namespace App\Enerjisa\Services;

use RuntimeException;

class MdmException extends RuntimeException
{
    /** @param array<string, string|int|float> $diagnostics */
    public function __construct(string $message, public readonly array $diagnostics = [], public readonly ?int $serviceCode = null)
    {
        parent::__construct($message);
    }
}
