<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the canonical Ramadan window for the years the project has
 * been operating through. The dates are approximate; production teams
 * usually override them with the locally observed moon-sighting dates
 * at the start of each Ramadan.
 */
class RamadanDatesSeeder extends Seeder
{
    /**
     * The default Ramadan schedule used by the application.
     *
     * The dates are derived from the Umm al-Qura calendar and are
     * refreshed at the start of every Ramadan. Each row contains the
     * reduced working hours that the attendance policy enforces for
     * that month.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $schedule = [
        ['year' => 2024, 'start_date' => '2024-03-11', 'end_date' => '2024-04-09'],
        ['year' => 2025, 'start_date' => '2025-02-28', 'end_date' => '2025-03-29'],
        ['year' => 2026, 'start_date' => '2026-02-17', 'end_date' => '2026-03-18'],
        ['year' => 2027, 'start_date' => '2027-02-06', 'end_date' => '2027-03-07'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        foreach ($this->schedule as $row) {
            DB::table('ramadan_dates')->updateOrInsert(
                ['year' => $row['year']],
                array_merge($row, [
                    'daily_working_hours' => 6,
                    'default_start_time' => '09:00:00',
                    'default_end_time' => '15:00:00',
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }
    }
}
