<?php

namespace Modules\Shifts\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ShiftCategoryValidationService
{
    /**
     * Validate data for creating a new shift category.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateCreate(array $data): array
    {
        $rules = $this->buildRules($data);

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Validate data for updating an existing shift category.
     *
     * @param  object|null  $category
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateUpdate($category, array $data): array
    {
        $rules = $this->buildRules($data, isUpdate: true);

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Build validation rules based on the shift category type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function buildRules(array $data, bool $isUpdate = false): array
    {
        $rules = [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'type' => [$isUpdate ? 'sometimes' : 'required', 'string', 'in:cyclic,weekly,hours'],
            'time_schedule_id' => ['nullable', 'integer', 'exists:att_time_schedules,id'],
            // Dynamic shift engine fields (Step 1) — optional, safe to omit.
            'anchor_start_date' => ['nullable', 'date'],
            'cycle_length' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_dynamic' => ['nullable', 'boolean'],
        ];

        $type = $data['type'] ?? null;

        if ($type === 'cyclic') {
            $rules['work_days'] = ['required', 'integer', 'min:1'];
            $rules['rest_days'] = ['required', 'integer', 'min:0'];
            // When dynamic, the group anchor drives the engine math.
            if (! empty($data['is_dynamic'])) {
                $rules['anchor_start_date'] = ['required', 'date'];
            }
        } elseif ($type === 'weekly') {
            $rules['work_days_json'] = ['required', 'array', 'min:1'];
            $rules['weekend_days_json'] = ['nullable', 'array'];
        } elseif ($type === 'hours') {
            $rules['required_hours'] = ['required', 'numeric', 'min:0.01'];
            $rules['period_type'] = ['required', 'in:daily,weekly,monthly'];
        }

        return $rules;
    }
}
