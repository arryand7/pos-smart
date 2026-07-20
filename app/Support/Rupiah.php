<?php

namespace App\Support;

final class Rupiah
{
    public static function from(mixed $value, string $field = 'nominal'): int
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new MoneyValueException($field, $value);
            }

            return $value;
        }

        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]*)(?:\.00)?$/', $value)) {
            throw new MoneyValueException($field, $value);
        }

        $integer = strstr($value, '.', true);
        $integer = $integer === false ? $value : $integer;

        if (strlen($integer) > strlen((string) PHP_INT_MAX)
            || (strlen($integer) === strlen((string) PHP_INT_MAX) && strcmp($integer, (string) PHP_INT_MAX) > 0)) {
            throw new MoneyValueException($field, $value);
        }

        return (int) $integer;
    }
}
