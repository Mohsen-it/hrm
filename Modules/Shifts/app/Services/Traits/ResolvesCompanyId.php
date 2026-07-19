<?php

namespace Modules\Shifts\Services\Traits;

use Illuminate\Validation\ValidationException;
use Modules\Companies\Models\Company;

trait ResolvesCompanyId
{
    /**
     * Resolve the company_id from the authenticated user.
     * Falls back to the first active company when the current user
     * is the system super-admin (id=10000) which has no company.
     */
    protected function resolveCompanyId(): int
    {
        $user = auth()->user();

        if ($user && ! empty($user->company_id)) {
            return (int) $user->company_id;
        }

        $company = Company::query()
            ->where('status', 1)
            ->orderBy('id')
            ->first();

        if (! $company) {
            throw ValidationException::withMessages([
                'company_id' => [__('shifts.no_active_company')],
            ]);
        }

        return (int) $company->id;
    }
}
