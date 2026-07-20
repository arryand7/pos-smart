<?php

namespace App\Console\Commands;

use App\Support\MoneyColumnAuditor;
use Illuminate\Console\Command;

class AuditMoneyColumns extends Command
{
    protected $signature = 'smart:audit-money-columns';

    protected $description = 'Audit read-only nominal rupiah yang memiliki pecahan atau format tidak valid';

    public function handle(MoneyColumnAuditor $auditor): int
    {
        $issues = $auditor->audit();

        $this->info('SMART Money Column Audit (read-only)');
        if ($issues === []) {
            $this->info('OK: seluruh nominal yang diperiksa merupakan rupiah bulat yang valid.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'ID', 'Column', 'Value', 'Issue'], array_map(fn ($issue) => [
            $issue['table'], $issue['id'], $issue['column'], (string) $issue['value'], $issue['error'],
        ], $issues));
        $this->error(count($issues).' masalah nominal ditemukan. Tidak ada data yang diubah.');

        return self::FAILURE;
    }
}
