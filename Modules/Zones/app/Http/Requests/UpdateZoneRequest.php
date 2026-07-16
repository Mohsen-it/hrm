<?php

namespace Modules\Zones\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Zones\Enums\ZoneType;

/**
 * UpdateZoneRequest — validation for the update-zone payload.
 */
class UpdateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $zoneId = (int) $this->route('zone');
        $allowed = array_map(fn (ZoneType $t) => $t->value, ZoneType::cases());

        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:50', 'unique:zones,code,'.$zoneId],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'zone_type' => ['nullable', 'string', Rule::in($allowed)],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
