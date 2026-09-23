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
            'in_ahead_margin' => ['nullable', 'integer', 'min:0'],
            'in_above_margin' => ['nullable', 'integer', 'min:0'],
            'out_ahead_margin' => ['nullable', 'integer', 'min:0'],
            'out_above_margin' => ['nullable', 'integer', 'min:0'],
            'third_punch_start' => ['nullable', 'date_format:H:i'],
            'third_punch_end' => ['nullable', 'date_format:H:i'],
        ];

        $validated = Validator::make($data, $rules)->validate();
        $this->assertThirdPunchRange($validated);

        return $validated;
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
            'in_ahead_margin' => ['nullable', 'integer', 'min:0'],
            'in_above_margin' => ['nullable', 'integer', 'min:0'],
            'out_ahead_margin' => ['nullable', 'integer', 'min:0'],
            'out_above_margin' => ['nullable', 'integer', 'min:0'],
            'third_punch_start' => ['nullable', 'date_format:H:i'],
            'third_punch_end' => ['nullable', 'date_format:H:i'],
        ];

        $validated = Validator::make($data, $rules)->validate();

        // On partial updates either edge may come from the stored row — but
        // only when the key is absent from the payload (an explicit null
        // clears the edge instead of inheriting the stored value).
        $start = array_key_exists('third_punch_start', $validated)
            ? $validated['third_punch_start']
            : $schedule?->third_punch_start;
        $end = array_key_exists('third_punch_end', $validated)
            ? $validated['third_punch_end']
            : $schedule?->third_punch_end;
        $this->assertThirdPunchRange(['third_punch_start' => $start, 'third_punch_end' => $end]);

        return $validated;
    }

    /**
     * The third (evening) punch window is a same-day interval: when both
     * edges are set, the end must fall after the start.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertThirdPunchRange(array $data): void
    {
        $start = $data['third_punch_start'] ?? null;
        $end = $data['third_punch_end'] ?? null;

        if ($start === null || $start === '' || $end === null || $end === '') {
            return;
        }

        if (substr((string) $end, 0, 5) <= substr((string) $start, 0, 5)) {
            throw ValidationException::withMessages([
                'third_punch_end' => [__('shifts.third_punch_invalid_range')],
            ]);
        }
    }
}
