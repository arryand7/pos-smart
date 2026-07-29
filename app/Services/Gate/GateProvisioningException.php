<?php

namespace App\Services\Gate;

use RuntimeException;

class GateProvisioningException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }
}
