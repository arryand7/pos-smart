<?php

namespace Tests\Unit;

use App\Support\MoneyValueException;
use App\Support\Rupiah;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RupiahTest extends TestCase
{
    public static function validValues(): array
    {
        return [[10000, 10000], ['10000', 10000], ['10000.00', 10000], ['0.00', 0]];
    }

    #[DataProvider('validValues')]
    public function test_accepts_whole_rupiah(int|string $input, int $expected): void
    {
        $this->assertSame($expected, Rupiah::from($input));
    }

    public static function invalidValues(): array
    {
        return [['10000.50'], [10000.5], [-1], ['-1.00'], ['NaN'], [INF], [null]];
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_fractional_float_negative_and_non_numeric_values(mixed $input): void
    {
        $this->expectException(MoneyValueException::class);
        Rupiah::from($input);
    }
}
