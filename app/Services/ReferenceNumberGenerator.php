<?php

namespace App\Services;

use App\Models\WorkType;
use Illuminate\Support\Facades\DB;

class ReferenceNumberGenerator
{
    public function next(WorkType $workType): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($workType, $year): string {
            $sequence = DB::table('reference_sequences')
                ->where('work_type_id', $workType->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                DB::table('reference_sequences')->insert([
                    'work_type_id' => $workType->id,
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $next = 1;
            } else {
                $next = $sequence->last_number + 1;
                DB::table('reference_sequences')
                    ->where('id', $sequence->id)
                    ->update([
                        'last_number' => $next,
                        'updated_at' => now(),
                    ]);
            }

            return sprintf('%s-%d-%05d', $workType->reference_prefix, $year, $next);
        });
    }
}
