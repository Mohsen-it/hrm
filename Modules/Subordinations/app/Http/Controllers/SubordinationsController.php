<?php

namespace Modules\Subordinations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Subordinations\Http\Requests\StoreSubordinationRequest;
use Modules\Subordinations\Http\Requests\UpdateSubordinationRequest;
use Modules\Subordinations\Models\Subordination;
use Modules\Subordinations\Services\SubordinationService;

class SubordinationsController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private SubordinationService $subordinationService,
    ) {}

    /**
     * Display a listing of subordinations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-subordinations');

        $filters = $this->cleanFilters($request->only(['search', 'status']));

        return Inertia::render('Subordinations/Index', [
            'filters' => fn () => $filters,
            'subordinations' => fn () => $this->subordinationService
                ->getAllSubordinations($filters, 20)
                ->through(fn (Subordination $s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'name_ar' => $s->name_ar,
                    'name_en' => $s->name_en,
                    'display_name' => $s->display_name,
                    'description' => $s->description,
                    'status' => (int) $s->status,
                    'sort_order' => (int) $s->sort_order,
                    'created_at' => $s->created_at?->format('Y-m-d H:i'),
                ]),
            'statusOptions' => fn () => [
                ['value' => 1, 'label' => __('subordinations.active')],
                ['value' => 0, 'label' => __('subordinations.inactive')],
            ],
        ]);
    }

    /**
     * Show the form for creating a new subordination.
     */
    public function create(): Response
    {
        $this->authorize('create-subordinations');

        return Inertia::render('Subordinations/Create', [
            'statusOptions' => fn () => $this->statusOptions(),
        ]);
    }

    /**
     * Persist a new subordination row.
     */
    public function store(StoreSubordinationRequest $request): RedirectResponse
    {
        $this->subordinationService->createSubordination($request->validated());

        return redirect()->route('subordinations.index')
            ->with('success', __('subordinations.created_successfully'));
    }

    /**
     * Display the specified subordination.
     */
    public function show(int $subordination): Response
    {
        $this->authorize('view-subordinations');

        $s = $this->subordinationService->getSubordinationById($subordination);
        if (! $s) {
            abort(404);
        }

        return Inertia::render('Subordinations/Show', [
            'subordination' => fn () => $this->present($s),
        ]);
    }

    /**
     * Show the form for editing the specified subordination.
     */
    public function edit(int $subordination): Response
    {
        $this->authorize('edit-subordinations');

        $s = $this->subordinationService->getSubordinationById($subordination);
        if (! $s) {
            abort(404);
        }

        return Inertia::render('Subordinations/Edit', [
            'subordination' => fn () => $this->present($s),
            'statusOptions' => fn () => $this->statusOptions(),
        ]);
    }

    /**
     * Update the specified subordination.
     */
    public function update(UpdateSubordinationRequest $request, int $subordination): RedirectResponse
    {
        $this->authorize('edit-subordinations');

        $s = $this->subordinationService->getSubordinationById($subordination);
        if (! $s) {
            abort(404);
        }

        $this->subordinationService->updateSubordination($s, $request->validated());

        return redirect()->route('subordinations.index')
            ->with('success', __('subordinations.updated_successfully'));
    }

    /**
     * Soft-delete the specified subordination.
     */
    public function destroy(int $subordination): RedirectResponse
    {
        $this->authorize('delete-subordinations');

        $s = $this->subordinationService->getSubordinationById($subordination);
        if (! $s) {
            abort(404);
        }

        $this->subordinationService->deleteSubordination($s);

        return redirect()->route('subordinations.index')
            ->with('success', __('subordinations.deleted_successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Subordination $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'name_ar' => $s->name_ar,
            'name_en' => $s->name_en,
            'display_name' => $s->display_name,
            'description' => $s->description,
            'status' => (int) $s->status,
            'sort_order' => (int) $s->sort_order,
            'created_at' => $s->created_at?->format('Y-m-d H:i'),
            'updated_at' => $s->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function statusOptions(): array
    {
        return [
            ['value' => 1, 'label' => __('subordinations.active')],
            ['value' => 0, 'label' => __('subordinations.inactive')],
        ];
    }

    /**
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
     * Export subordinations to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-subordinations');

        $filters = $this->cleanFilters($request->only(['search', 'status']));
        $subordinations = $this->subordinationService->getAllSubordinations($filters, 10000);

        $headers = ['#', 'الرمز', 'الاسم بالعربية', 'الاسم بالإنجليزية', 'الوصف', 'ترتيب العرض', 'الحالة'];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'code' => ['key' => 'code', 'type' => 'string', 'width' => 15],
            'name_ar' => ['key' => 'name_ar', 'type' => 'string', 'width' => 25],
            'name_en' => ['key' => 'name_en', 'type' => 'string', 'width' => 25],
            'description' => ['key' => 'description', 'type' => 'string', 'width' => 35],
            'sort_order' => ['key' => 'sort_order', 'type' => 'integer', 'width' => 12],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 12,
                'map' => [1 => 'نشط', 0 => 'غير نشط'],
                'status_color' => [
                    1 => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    0 => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
        ];

        return $this->quickExcelExport('قائمة الإدارات', $headers, $subordinations->getCollection(), $columns, 'subordinations');
    }
}
