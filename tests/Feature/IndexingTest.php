<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_composite_indexes_exist(): void
    {
        $this->artisan('migrate');
        $indexes = $this->getTableIndexes('users');

        $expected = [
            'idx_users_company_status_active',
            'idx_users_branch_status',
            'idx_users_department_status',
            'idx_users_position_status',
            'idx_users_grade_status',
            'idx_users_employment_type',
            'idx_users_hire_date',
        ];

        $found = array_keys($indexes);
        $missing = array_diff($expected, $found);
        $this->assertEmpty($missing, 'Missing: '.implode(', ', $missing).'. Found: '.implode(', ', $found));
    }

    public function test_attendance_sessions_composite_indexes_exist(): void
    {
        $this->artisan('migrate');
        $indexes = $this->getTableIndexes('attendance_sessions');

        $expected = [
            'idx_att_sessions_user_date_status',
            'idx_att_sessions_date_status_type',
            'idx_att_sessions_created_by',
            'idx_att_sessions_checkout',
        ];

        $found = array_keys($indexes);
        $missing = array_diff($expected, $found);
        $this->assertEmpty($missing, 'Missing: '.implode(', ', $missing).'. Found: '.implode(', ', $found));
    }

    public function test_raw_attendance_logs_composite_indexes_exist(): void
    {
        $this->artisan('migrate');
        $indexes = $this->getTableIndexes('raw_attendance_logs');

        $expected = [
            'idx_raw_logs_dedup',
            'idx_raw_logs_user_time',
            'idx_raw_logs_processed_punch',
        ];

        $found = array_keys($indexes);
        $missing = array_diff($expected, $found);
        $this->assertEmpty($missing, 'Missing: '.implode(', ', $missing).'. Found: '.implode(', ', $found));
    }

    public function test_users_query_uses_indexes(): void
    {
        $this->artisan('migrate');
        $query = DB::table('users')
            ->select('id', 'company_id', 'branch_id', 'status')
            ->where('company_id', 1)
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->limit(20);

        $explain = DB::select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings());
        $explainText = collect($explain)->pluck('detail')->implode(' ');

        $this->assertStringNotContainsString('SCAN', $explainText, 'Full table scan detected on users query');
    }

    public function test_active_company_query_uses_index(): void
    {
        $this->artisan('migrate');
        $query = DB::table('users')
            ->select('id')
            ->where('company_id', 1)
            ->where('status', 'active')
            ->orderBy('id', 'desc');

        $explain = DB::select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings());
        $explainText = collect($explain)->pluck('detail')->implode(' ');

        $this->assertStringNotContainsString('SCAN', $explainText, 'Full table scan detected on active+company query');
    }

    private function getTableIndexes(string $table): array
    {
        $indexes = DB::select("PRAGMA index_list('{$table}')");
        $result = [];

        foreach ($indexes as $index) {
            $info = DB::select("PRAGMA index_info('{$index->name}')");
            $columns = array_map(fn ($col) => $col->name, $info);
            $result[$index->name] = $columns;
        }

        return $result;
    }
}
