<?php

namespace Modules\Shifts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shifts\Enums\ShiftCategoryType;
use Modules\Shifts\Http\Requests\StoreShiftCategoryRequest;
use Modules\Shifts\Http\Requests\UpdateShiftCategoryRequest;
use Modules\Shifts\Http\Resources\ShiftCategoryResource;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Services\CyclicScheduleCalculator;
use Modules\Shifts\Services\ShiftCategoryService;
use Modules\Shifts\Services\TimeScheduleService;

class ShiftCategoriesController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private ShiftCategoryService $shiftCategoryService,
        private TimeScheduleService $timeScheduleService,
        private CyclicScheduleCalculator $cyclicCalculator
    ) {}

    /**
     * Display a listing of shift categories.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-shift-categories');

        return Inertia::render('Shifts/ShiftCategories/Index', [
            'filters' => fn () => $request->only(['search', 'type', 'company_id']),
            'categories' => fn () => ShiftCategoryResource::collection(
                $this->shiftCategoryService->getAll(
                    $request->only(['search', 'type', 'company_id'])
                )
            ),
            'types' => ShiftCategoryType::options(),
        ]);
    }

    /**
     * Show the form for creating a new shift category.
     */
    public function create(): Response
    {
        $this->authorize('create-shift-categories');

        return Inertia::render('Shifts/ShiftCategories/Create', [
            'timeSchedules' => fn () => $this->timeScheduleService->getList(),
            'types' => ShiftCategoryType::options(),
        ]);
    }

    /**
     * Store a newly created shift category.
     */
    public function store(StoreShiftCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create-shift-categories');

        $this->shiftCategoryService->create($request->validated());

        return redirect()->route('shift-categories.index')
            ->with('success', __('shifts.category_created'));
    }

    /**
     * Display the specified shift category.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-shift-categories');

        $category = $this->shiftCategoryService->getById($id);

        if (! $category) {
            abort(404);
        }

        return Inertia::render('Shifts/ShiftCategories/Show', [
            'category' => new ShiftCategoryResource($category->load('timeSchedule', 'employees')),
        ]);
    }

    /**
     * Show the schedule preview for a shift category.
     */
    public function schedulePreview(int $id, Request $request): Response
    {
        $this->authorize('view-shift-categories');

        $category = $this->shiftCategoryService->getById($id);

        if (! $category) {
            abort(404);
        }

        $from = $request->get('from', now()->toDateString());
        $to = $request->get('to', now()->addYears(5)->toDateString());

        // Find an active assignment for this category to get the cycle start date
        $assignment = EmployeeShiftCategory::where('shift_category_id', $id)
            ->whereNull('end_date')
            ->first();

        $cycleStart = $assignment?->start_date ? Carbon::parse($assignment->start_date) : now();

        $schedule = [];
        if ($category->type === 'cyclic') {
            $workDays = (int) ($category->work_days ?? 0);
            $restDays = (int) ($category->rest_days ?? 0);
            $schedule = $this->cyclicCalculator->getScheduleInRange(
                $cycleStart,
                $workDays,
                $restDays,
                Carbon::parse($from),
                Carbon::parse($to)
            );
        } elseif ($category->type === 'weekly') {
            $workDaysJson = $category->work_days_json;
            $workDaysArray = is_array($workDaysJson) ? $workDaysJson : [];
            $current = Carbon::parse($from);
            $endDate = Carbon::parse($to);
            while ($current->lte($endDate)) {
                $isWorkDay = in_array($current->dayOfWeek, $workDaysArray);
                $schedule[] = [
                    'date' => $current->format('Y-m-d'),
                    'is_work_day' => $isWorkDay,
                ];
                $current->addDay();
            }
        } else {
            // hours type - all days are potential work days
            $current = Carbon::parse($from);
            $endDate = Carbon::parse($to);
            while ($current->lte($endDate)) {
                $schedule[] = [
                    'date' => $current->format('Y-m-d'),
                    'is_work_day' => true,
                ];
                $current->addDay();
            }
        }

        return Inertia::render('Shifts/ShiftCategories/SchedulePreview', [
            'category' => fn () => new ShiftCategoryResource($category->load('timeSchedule')),
            'assignment_start' => $cycleStart->format('Y-m-d'),
            'from' => $from,
            'to' => $to,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Show the form for editing the specified shift category.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-shift-categories');

        $category = $this->shiftCategoryService->getById($id);

        if (! $category) {
            abort(404);
        }

        return Inertia::render('Shifts/ShiftCategories/Edit', [
            'category' => fn () => new ShiftCategoryResource($category),
            'timeSchedules' => fn () => $this->timeScheduleService->getList(),
        ]);
    }

    /**
     * Update the specified shift category.
     */
    public function update(UpdateShiftCategoryRequest $request, int $id): RedirectResponse
    {
        $this->authorize('edit-shift-categories');

        $this->shiftCategoryService->update($id, $request->validated());

        return redirect()->route('shift-categories.index')
            ->with('success', __('shifts.category_updated'));
    }

    /**
     * Remove the specified shift category.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-shift-categories');

        $this->shiftCategoryService->delete($id);

        return redirect()->route('shift-categories.index')
            ->with('success', __('shifts.category_deleted'));
    }

    /**
     * Export shift categories to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-shift-categories');

        $categories = $this->shiftCategoryService->getAll(
            $request->only(['search', 'type', 'company_id'])
        );

        $headers = ['#', 'اسم الفئة', 'النوع', 'أيام العمل', 'أيام الراحة', 'الشركة', 'الوصف'];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'type' => [
                'key' => 'type',
                'type' => 'status',
                'width' => 15,
                'map' => [
                    'cyclic' => 'دوري',
                    'weekly' => 'أسبوعي',
                    'hours' => 'ساعات',
                ],
            ],
            'work_days' => ['key' => 'work_days', 'type' => 'integer', 'width' => 12],
            'rest_days' => ['key' => 'rest_days', 'type' => 'integer', 'width' => 12],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 25],
            'description' => ['key' => 'description', 'type' => 'string', 'width' => 35],
        ];

        return $this->quickExcelExport('فئات الورديات', $headers, $categories->getCollection(), $columns, 'shift-categories');
    }
}
