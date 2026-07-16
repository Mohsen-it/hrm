<?php

namespace Modules\Vacations\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Vacations\Models\UserVacationBalanceTransaction;

/**
 * Repository for `UserVacationBalanceTransaction`.
 *
 * Append-only — the repository exposes only `create()` and read
 * helpers. The corresponding `update()` / `delete()` are intentionally
 * missing to keep the audit trail immutable.
 */
class UserVacationBalanceTransactionRepository
{
    /**
     * Append a transaction row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): UserVacationBalanceTransaction
    {
        return UserVacationBalanceTransaction::create($data);
    }

    /**
     * List every transaction logged for a given balance.
     *
     * @return Collection<int, UserVacationBalanceTransaction>
     */
    public function listForBalance(int $balanceId, int $limit = 100): Collection
    {
        return UserVacationBalanceTransaction::query()
            ->where('balance_id', $balanceId)
            ->with(['user', 'vacationType', 'creator'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * List every transaction logged for a given user.
     *
     * @return Collection<int, UserVacationBalanceTransaction>
     */
    public function listForUser(int $userId, int $limit = 100): Collection
    {
        return UserVacationBalanceTransaction::query()
            ->where('user_id', $userId)
            ->with(['vacationType', 'creator'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Count transactions matching the supplied filter bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        $query = UserVacationBalanceTransaction::query();

        foreach (['user_id', 'vacation_type_id', 'type', 'reference_type', 'reference_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->count();
    }

    /**
     * Resolve a Model instance to a "morph-like" reference pair, falling back
     * to a plain id when the value is not a Model.
     *
     * @return array{reference_type: string|null, reference_id: int|null}
     */
    public function resolveReference(mixed $reference): array
    {
        if ($reference instanceof Model) {
            return [
                'reference_type' => $reference->getMorphClass(),
                'reference_id' => (int) $reference->getKey(),
            ];
        }

        if (is_array($reference) && isset($reference['reference_type'], $reference['reference_id'])) {
            return [
                'reference_type' => (string) $reference['reference_type'],
                'reference_id' => (int) $reference['reference_id'],
            ];
        }

        return ['reference_type' => null, 'reference_id' => null];
    }
}
