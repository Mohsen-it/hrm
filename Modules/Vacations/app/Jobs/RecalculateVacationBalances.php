<?php

namespace Modules\Vacations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\Users\Models\User;
use Modules\Vacations\Repositories\VacationTypeRepository;
use Modules\Vacations\Services\VacationBalanceService;

/**
 * RecalculateVacationBalances — bulk grant the default entitlement to
 * every active employee for the supplied year.
 *
 * Typical callers:
 *  - Year-end cron (run on 1 January for the new year).
 *  - Onboarding of a batch of new employees.
 *  - Operator-triggered "rebuild year" button.
 *
 * The job is idempotent: a second invocation only tops up the
 * entitlement delta, never re-grants a full year.
 */
class RecalculateVacationBalances implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of seconds the job may run before timing out.
     */
    public int $timeout = 1800;

    /**
     * Number of users processed per chunk.
     */
    public int $chunk = 200;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $year,
        public ?int $vacationTypeId = null,
    ) {}

    /**
     * Execute the job.
     *
     * @return array{processed:int, granted_total:int}
     */
    public function handle(
        VacationBalanceService $balanceService,
        VacationTypeRepository $typeRepository,
    ): array {
        $types = $this->vacationTypeId
            ? $typeRepository->query()->where('id', $this->vacationTypeId)->get()
            : $typeRepository->query()->where('is_active', true)->get();

        if ($types->isEmpty()) {
            return ['processed' => 0, 'granted_total' => 0];
        }

        $processed = 0;
        $granted = 0;

        User::query()
            ->withoutSuperAdmin()
            ->where('status', 1)
            ->where('is_active_employee', true)
            ->whereNull('termination_date')
            ->select(['id'])
            ->chunkById($this->chunk, function ($users) use ($balanceService, $types, &$processed, &$granted): void {
                DB::beginTransaction();
                try {
                    foreach ($users as $user) {
                        foreach ($types as $type) {
                            $balance = $balanceService->grant(
                                (int) $user->id,
                                $type,
                                $this->year,
                                (int) $type->default_days_per_year,
                                null,
                                true,
                                [
                                    'reference_type' => 'year_grant',
                                    'reference_id' => $this->year,
                                ],
                            );
                            $granted += (int) $type->default_days_per_year;
                        }
                        $processed++;
                    }
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            });

        return ['processed' => $processed, 'granted_total' => $granted];
    }
}
