<?php

namespace App\Services\POS;

use RuntimeException;

class PosTransactionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
