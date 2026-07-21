<?php

namespace Modules\Holidays\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Holidays\Http\Requests\StoreHolidayRequest;
use Modules\Holidays\Http\Requests\UpdateHolidayRequest;
use Modules\Holidays\Jobs\SyncHolidaysToAttendance;
use Modules\Holidays\Models\Holiday;
use Modules\Holidays\Services\HolidayService;

/**
 * HolidaysController — CRUD on the holiday calendar.
 *
 * The page is read-mostly: a list of upcoming / past holidays, a
 * create / edit / delete form, and a quick "sync to attendance" button
 * that dispatches the integration job.
 */
class HolidaysController extends Controller
{
    use ExcelExportable;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private HolidayService $holidayService,
    ) {}

    /**
     * Display a listing of holidays.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-holidays');

        $filters = $this->cleanFilters($request->only([
            'search', 'category', 'is_active', 'is_recurring', 'date', 'from', 'to', 'year',
        ]));

        $upcoming = [];
        $today = new \DateTimeImmutable('today');
        $from = $today->modify('-7 days')->format('Y-m-d');
        $to = $today->modify('+90 days')->format('Y-m-d');

        foreach ($this->holidayService->getActiveInRange($from, $to) as $holiday) {
            /** @var Holiday $holiday */
            foreach ($holiday->occurrencesInRange($from, $to) as $date) {
                $upcoming[] = [
                    'id' => $holiday->id,
                    'name_ar' => $holiday->name_ar,
                    'name_en' => $holiday->name_en,
                    'category' => $holiday->category,
                    'date' => $date,
                    'is_recurring' => (bool) $holiday->is_recurring,
                ];
            }
        }
        usort($upcoming, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return Inertia::render('Holidays/Index', [
            'filters' => fn () => $filters,
            'holidays' => fn () => $this->holidayService->getAllHolidays($filters, 20)
                ->through(fn (Holiday $h) => $this->present($h)),
            'upcoming' => fn () => $upcoming,
        ]);
    }

    /**
     * Show the form for creating a new holiday.
     */
    public function create(): Response
    {
        $this->authorize('create-holidays');

        return Inertia::render('Holidays/Create');
    }

    /**
     * Persist a new holiday row.
     */
    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $this->authorize('create-holidays');

        $holiday = $this->holidayService->createHoliday($request->validated());

        $this->dispatchSync($holiday);

        return redirect()->route('holidays.index')
            ->with('success', __('holidays.created_successfully'));
    }

    /**
     * Display the specified holiday.
     */
    public function show(int $holiday): Response
    {
        $this->authorize('view-holidays');

        $h = $this->holidayService->findHoliday($holiday);
        if (! $h) {
            abort(404);
        }

        return Inertia::render('Holidays/Show', [
            'holiday' => fn () => $this->present($h),
        ]);
    }

    /**
     * Show the form for editing the specified holiday.
     */
    public function edit(int $holiday): Response
    {
        $this->authorize('edit-holidays');

        $h = $this->holidayService->findHoliday($holiday);
        if (! $h) {
            abort(404);
        }

        return Inertia::render('Holidays/Edit', [
            'holiday' => fn () => $this->present($h),
        ]);
    }

    /**
     * Update the specified holiday.
     */
    public function update(UpdateHolidayRequest $request, int $holiday): RedirectResponse
    {
        $this->authorize('edit-holidays');

        $h = $this->holidayService->findHoliday($holiday);
        if (! $h) {
            abort(404);
        }

        $updated = $this->holidayService->updateHoliday($h, $request->validated());

        $this->dispatchSync($updated);

        return redirect()->route('holidays.index')
            ->with('success', __('holidays.updated_successfully'));
    }

    /**
     * Soft delete the specified holiday.
     */
    public function destroy(int $holiday): RedirectResponse
    {
        $this->authorize('delete-holidays');

        $h = $this->holidayService->findHoliday($holiday);
        if (! $h) {
            abort(404);
        }

        $this->holidayService->deleteHoliday($h);

        return redirect()->route('holidays.index')
            ->with('success', __('holidays.deleted_successfully'));
    }

    /**
     * Sync the holiday calendar to `daily_attendance_summaries` for the
     * supplied range. Useful as an "operator rebuild" button.
     */
    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('edit-holidays');

        $from = (string) $request->input('from', now()->format('Y-m-d'));
        $to = (string) $request->input('to', now()->modify('+90 days')->format('Y-m-d'));

        SyncHolidaysToAttendance::dispatch($from, $to);

        return redirect()->route('holidays.index')
            ->with('success', __('holidays.sync_queued'));
    }

    /**
     * Render the supplied holiday into a frontend-friendly array.
     *
     * @return array<string, mixed>
     */
    protected function present(Holiday $h): array
    {
        return [
            'id' => $h->id,
            'name_ar' => $h->name_ar,
            'name_en' => $h->name_en,
            'code' => $h->code,
            'is_recurring' => (bool) $h->is_recurring,
            'date' => $h->date?->format('Y-m-d'),
            'recurring_month' => $h->recurring_month,
            'recurring_day' => $h->recurring_day,
            'category' => $h->category,
            'is_paid' => (bool) $h->is_paid,
            'is_active' => (bool) $h->is_active,
            'duration_days' => (int) $h->duration_days,
            'applies_to_all' => (bool) $h->applies_to_all,
            'applies_to_branches' => $h->applies_to_branches ?? [],
            'applies_to_departments' => $h->applies_to_departments ?? [],
            'description' => $h->description,
            'created_at' => $h->created_at?->format('Y-m-d H:i'),
            'updated_at' => $h->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * Dispatch the `SyncHolidaysToAttendance` job for the given holiday.
     * The job spans `lookback_days` to `lookahead_days` from today.
     */
    protected function dispatchSync(Holiday $h): void
    {
        $today = new \DateTimeImmutable('today');
        $lookback = (int) config('holidays.lookback_days', 7);
        $lookahead = (int) config('holidays.lookahead_days', 365);

        $from = $today->modify("-{$lookback} days")->format('Y-m-d');
        $to = $today->modify("+{$lookahead} days")->format('Y-m-d');

        SyncHolidaysToAttendance::dispatch($from, $to);
    }

    /**
     * Drop empty / null entries from a filter bag so the URL stays clean.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter(
            $filters,
            fn ($v) => $v !== null && $v !== '' && $v !== [],
        );
    }

    /**
     * Export holidays to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-holidays');

        $holidays = $this->holidayService->getAllHolidays(
            $this->cleanFilters($request->only([
                'search', 'category', 'is_active', 'is_recurring', 'date', 'from', 'to', 'year',
            ])),
            10000
        );

        $headers = ['#', 'الاسم بالعربية', 'الاسم بالإنجليزية', 'الرمز', 'التصنيف', 'التاريخ', 'متكرر', 'مدفوع', 'نشط'];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name_ar' => ['key' => 'name_ar', 'type' => 'string', 'width' => 25],
            'name_en' => ['key' => 'name_en', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'code', 'type' => 'string', 'width' => 15],
            'category' => [
                'key' => 'category',
                'type' => 'status',
                'width' => 15,
                'map' => [
                    'national' => 'وطني',
                    'religious' => 'ديني',
                    'official' => 'رسمي',
                    'company' => 'شركة',
                ],
            ],
            'date' => ['key' => 'date', 'type' => 'string', 'width' => 15],
            'is_recurring' => ['key' => 'is_recurring', 'type' => 'boolean', 'width' => 10],
            'is_paid' => ['key' => 'is_paid', 'type' => 'boolean', 'width' => 10],
            'is_active' => [
                'key' => 'is_active',
                'type' => 'status',
                'width' => 10,
                'map' => [true => 'نشط', false => 'غير نشط'],
                'status_color' => [
                    true => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    false => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
        ];

        return $this->quickExcelExport('قائمة الإجازات', $headers, $holidays->getCollection(), $columns, 'holidays');
    }
}
