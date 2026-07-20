<?php

namespace App\Support;

use DomainException;

class MoneyValueException extends DomainException
{
    public function __construct(public readonly string $field, mixed $value, bool $allowNegative = false)
    {
        $requirement = $allowNegative ? 'rupiah bulat tanpa pecahan' : 'rupiah bulat non-negatif tanpa pecahan';

        parent::__construct("Nilai {$field} harus berupa {$requirement}.");
    }
}
