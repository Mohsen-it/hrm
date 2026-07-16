<?php

namespace Modules\Shifts\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TimeScheduleValidationService
{
    /**
     * Validate data for creating a new time schedule.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateCreate(array $data): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'in_time' => ['required', 'date_format:H:i'],
            'out_time' => ['required', 'date_format:H:i'],
            'is_multi_day' => ['boolean'],
            'late_margin' => ['integer', 'min:0'],
            'early_margin' => ['integer', 'min:0'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Validate data for updating an existing time schedule.
     *
     * @param  object|null  $schedule
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateUpdate($schedule, array $data): array
    {
        $rules = [
            'name' => ['sometimes', 'string', 'max:100'],
            'in_time' => ['sometimes', 'date_format:H:i'],
            'out_time' => ['sometimes', 'date_format:H:i'],
            'is_multi_day' => ['boolean'],
            'late_margin' => ['integer', 'min:0'],
            'early_margin' => ['integer', 'min:0'],
        ];

        return Validator::make($data, $rules)->validate();
    }
}
