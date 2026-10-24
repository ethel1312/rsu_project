<?php

namespace App\Console\Commands;

use App\Models\Employees\Contract;
use App\Models\Employees\VacationBalance;
use Illuminate\Console\Command;

class GenerateVacationBalances extends Command
{
    protected $signature = 'vacations:generate-balances {year?}';

    protected $description = 'Genera los saldos anuales de vacaciones para el personal elegible';

    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?? now()->year);

        $contracts = Contract::with('employee')
            ->whereIn('contract_type', ['Permanente', 'Nombrado'])
            ->where('start_date', '<=', "$year-12-31")
            ->where(function ($query) use ($year) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', "$year-01-01");
            })
            ->get();

        $created = 0;
        $existing = 0;

        foreach ($contracts as $contract) {
            if (!$contract->employee || !$contract->employee->status) {
                continue;
            }

            $balance = VacationBalance::firstOrCreate(
                [
                    'employee_id' => $contract->employee_id,
                    'year' => $year,
                ],
                [
                    'days_entitled' => 30,
                    'days_taken' => 0,
                ]
            );

            if ($balance->wasRecentlyCreated) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->info("Año procesado: {$year}");
        $this->info("Saldos creados: {$created}");
        $this->info("Saldos que ya existían: {$existing}");

        return self::SUCCESS;
    }
}
