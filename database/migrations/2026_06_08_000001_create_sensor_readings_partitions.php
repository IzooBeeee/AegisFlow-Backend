<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $months = [
            ['year' => 2026, 'month' => 5],
            ['year' => 2026, 'month' => 6],
            ['year' => 2026, 'month' => 7],
            ['year' => 2026, 'month' => 8],
            ['year' => 2026, 'month' => 9],
            ['year' => 2026, 'month' => 12],
        ];

        foreach ($months as $m) {
            $year = $m['year'];
            $month = str_pad($m['month'], 2, '0', STR_PAD_LEFT);
            $nextMonth = $m['month'] == 12 ? '01' : str_pad($m['month'] + 1, 2, '0', STR_PAD_LEFT);
            $nextYear = $m['month'] == 12 ? $year + 1 : $year;

            $name = "sensor_readings_y{$year}m{$month}";
            $start = "{$year}-{$month}-01";
            $end = "{$nextYear}-{$nextMonth}-01";

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$name}
                PARTITION OF sensor_readings
                FOR VALUES FROM ('{$start}') TO ('{$end}')
            ");
        }
    }

    public function down(): void {}
};
