<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mirror every rotation pattern so the fixed RotationEngine keeps the
     * exact schedules users see today.
     *
     * Background: RotationEngine::isWorkDay() used to compute the cycle
     * position as (anchor - date) instead of (date - anchor), mirroring
     * every schedule around its anchor. All rotation patterns in the system
     * were tuned (by trial and error) against that mirrored behavior, so
     * fixing the engine flipped each rotation's work/rest days.
     *
     * Mirroring the pattern (new[i] = old[(cycle - i) % cycle]) exactly
     * compensates the sign fix: for every date, the new engine position
     * reads the old engine's value, so no employee's schedule changes.
     *
     * Rotation 15 is excluded: it was created after the engine fix and its
     * pattern ([1,1,1,1,0...]) was verified against the paper roster under
     * the corrected math. Mirroring is its own inverse, so down() simply
     * re-applies the same operation.
     */
    public function up(): void
    {
        DB::table('att_rotations')
            ->where('id', '!=', 15)
            ->orderBy('id')
            ->chunkById(50, function ($rotations): void {
                foreach ($rotations as $rotation) {
                    $mirrored = $this->mirroredPattern($rotation->pattern);

                    if ($mirrored === null) {
                        continue;
                    }

                    DB::table('att_rotations')
                        ->where('id', $rotation->id)
                        ->update([
                            'pattern' => $mirrored,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        $this->up();
    }

    /**
     * Mirror a stored JSON pattern, or null when it cannot be decoded.
     */
    private function mirroredPattern(mixed $pattern): ?string
    {
        $decoded = is_string($pattern)
            ? json_decode($pattern, true)
            : $pattern;

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        $values = array_values($decoded);
        $cycle = count($values);
        $mirrored = [];

        for ($i = 0; $i < $cycle; $i++) {
            $mirrored[$i] = $values[($cycle - $i) % $cycle];
        }

        return json_encode($mirrored, JSON_THROW_ON_ERROR);
    }
};
