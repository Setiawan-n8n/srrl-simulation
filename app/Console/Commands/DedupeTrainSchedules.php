<?php

namespace App\Console\Commands;

use App\Models\TrainSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeTrainSchedules extends Command
{
    /**
     * Removes duplicate train_schedules rows caused by a historical import
     * bug (see App\Support\JadwalImporter): duplicates share the exact
     * same (tanggal, urutan) pair. For each duplicate group this keeps the
     * lowest id (the original row) and deletes the rest.
     *
     * Defaults to a dry run — nothing is deleted unless --apply is passed.
     *
     * php artisan schedules:dedupe            # preview only
     * php artisan schedules:dedupe --apply     # actually delete duplicates
     */
    protected $signature = 'schedules:dedupe {--apply : Actually delete the duplicate rows (default is a dry run/preview)}';

    protected $description = 'Find and remove duplicate train_schedules rows that share the same date + row number';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $duplicateGroups = DB::table('train_schedules')
            ->select('tanggal', 'urutan', DB::raw('COUNT(*) as total'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('tanggal', 'urutan')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('tanggal')
            ->orderBy('urutan')
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate schedule rows found.');

            return self::SUCCESS;
        }

        $rows = [];
        $totalToDelete = 0;

        foreach ($duplicateGroups as $group) {
            $extra = $group->total - 1;
            $totalToDelete += $extra;
            $rows[] = [$group->tanggal, $group->urutan, $group->total, $extra];
        }

        $this->table(['Date', 'No.', 'Copies found', 'Extra rows to remove'], $rows);
        $this->newLine();
        $this->info(sprintf(
            '%d duplicate group(s), %d row(s) would be removed.',
            $duplicateGroups->count(),
            $totalToDelete
        ));

        if (! $apply) {
            $this->newLine();
            $this->comment('This was a dry run — nothing was deleted. Re-run with --apply to remove these rows.');

            return self::SUCCESS;
        }

        $deleted = 0;

        DB::transaction(function () use ($duplicateGroups, &$deleted) {
            foreach ($duplicateGroups as $group) {
                $deleted += TrainSchedule::query()
                    ->where('tanggal', $group->tanggal)
                    ->where('urutan', $group->urutan)
                    ->where('id', '!=', $group->keep_id)
                    ->delete();
            }
        });

        $this->newLine();
        $this->info("Deleted {$deleted} duplicate row(s).");

        return self::SUCCESS;
    }
}
