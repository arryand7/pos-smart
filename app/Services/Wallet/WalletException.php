<?php

namespace App\Services\Wallet;

use RuntimeException;

class WalletException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
