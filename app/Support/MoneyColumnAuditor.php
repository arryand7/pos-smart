<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MoneyColumnAuditor
{
    /** @return array<int, array{table:string,id:mixed,column:string,value:mixed,error:string}> */
    public function audit(): array
    {
        $issues = [];

        foreach ($this->columns() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $issues[] = ['table' => $table, 'id' => '-', 'column' => '-', 'value' => null, 'error' => 'Tabel tidak ditemukan.'];

                continue;
            }

            $columns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if ($columns === []) {
                continue;
            }

            DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')->chunkById(500, function ($rows) use (&$issues, $table, $columns) {
                foreach ($rows as $row) {
                    foreach ($columns as $column) {
                        try {
                            if ($this->allowsNegative($table, $column)) {
                                $this->assertSignedWholeRupiah($row->{$column}, "{$table}.{$column}");
                            } else {
                                Rupiah::from($row->{$column}, "{$table}.{$column}");
                            }
                        } catch (MoneyValueException $exception) {
                            $issues[] = [
                                'table' => $table,
                                'id' => $row->id,
                                'column' => $column,
                                'value' => $row->{$column},
                                'error' => $exception->getMessage(),
                            ];
                        }
                    }
                }
            });
        }

        return $issues;
    }

    /** @return array<string, array<int, string>> */
    public function columns(): array
    {
        return [
            'products' => ['cost_price', 'sale_price'],
            'santris' => ['wallet_balance', 'daily_limit', 'weekly_limit', 'monthly_limit'],
            'inventory_movements' => ['unit_cost', 'total_cost'],
            'wallet_transactions' => ['amount', 'balance_before', 'balance_after'],
            'transactions' => ['sub_total', 'discount_amount', 'tax_amount', 'total_amount', 'cash_amount', 'wallet_amount', 'gateway_amount', 'paid_amount', 'change_amount'],
            'transaction_items' => ['unit_price', 'discount_amount', 'subtotal'],
            'payments' => ['amount'],
            'journal_entries' => ['total_debit', 'total_credit'],
            'journal_lines' => ['amount'],
            'daily_closings' => ['cash_opening_balance', 'cash_total_sales', 'cash_total_topups', 'cash_deposited', 'cash_variance', 'wallet_sales', 'gateway_sales', 'total_transactions'],
        ];
    }

    private function allowsNegative(string $table, string $column): bool
    {
        return $table === 'daily_closings' && $column === 'cash_variance';
    }

    private function assertSignedWholeRupiah(mixed $value, string $field): void
    {
        if (is_int($value)) {
            return;
        }

        if (! is_string($value) || ! preg_match('/^-?(0|[1-9][0-9]*)(?:\.00)?$/', $value)) {
            throw new MoneyValueException($field, $value, true);
        }

        $integer = strstr(ltrim($value, '-'), '.', true);
        $integer = $integer === false ? ltrim($value, '-') : $integer;

        if (strlen($integer) > strlen((string) PHP_INT_MAX)
            || (strlen($integer) === strlen((string) PHP_INT_MAX) && strcmp($integer, (string) PHP_INT_MAX) > 0)) {
            throw new MoneyValueException($field, $value, true);
        }
    }
}
