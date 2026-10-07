<?php

namespace Modules\Settings\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Settings\Models\SystemLifecycle;

/**
 * SystemLifecycleRepository — الوصول لبيانات سجل دورة حياة النظام.
 */
class SystemLifecycleRepository
{
    public function record(array $data): SystemLifecycle
    {
        return SystemLifecycle::create($data);
    }

    public function latestBoot(): ?SystemLifecycle
    {
        return SystemLifecycle::boots()->latest('id')->first();
    }

    public function latestShutdown(): ?SystemLifecycle
    {
        return SystemLifecycle::shutdowns()->latest('id')->first();
    }

    /**
     * @return Collection<int, SystemLifecycle>
     */
    public function recent(int $limit = 50): Collection
    {
        return SystemLifecycle::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function countBoots(): int
    {
        return SystemLifecycle::boots()->count();
    }

    public function countUnclean(): int
    {
        // الإطفاء غير النظيف = boot جاء بعد boot دون shutdown بينهما.
        // نحسبها برمجياً في الـ Service؛ هنا عداد تقريبي سريع.
        $boots = SystemLifecycle::boots()->orderBy('id')->pluck('id')->all();
        $shutdowns = SystemLifecycle::shutdowns()->orderBy('id')->pluck('id')->all();

        $unclean = 0;
        $lastShutdownIdx = -1;
        foreach ($boots as $i => $bootId) {
            $hasShutdownAfterPrevious = $i === 0 ? true : $this->hasShutdownBetween($shutdowns, $boots[$i - 1], $bootId);
            if ($i > 0 && ! $hasShutdownAfterPrevious) {
                $unclean++;
            }
            unset($lastShutdownIdx);
        }

        return $unclean;
    }

    /**
     * @param  array<int>  $shutdownIds
     */
    private function hasShutdownBetween(array $shutdownIds, int $from, int $to): bool
    {
        foreach ($shutdownIds as $id) {
            if ($id > $from && $id < $to) {
                return true;
            }
        }

        return false;
    }
}
