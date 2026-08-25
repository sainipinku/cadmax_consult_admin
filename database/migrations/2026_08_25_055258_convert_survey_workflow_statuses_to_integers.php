<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    private const TABLES = [
        'construction_survey_plans' => [
            'default' => 1,
            'text_default' => 'planned',
            'values' => [
                'draft' => 0,
                'planned' => 1,
                'checked_in' => 2,
                'in_progress' => 2,
                'submitted' => 3,
                'approved' => 4,
                'revision_requested' => 5,
                'rejected' => 6,
            ],
            'reverse' => [
                0 => 'draft',
                1 => 'planned',
                2 => 'in_progress',
                3 => 'submitted',
                4 => 'approved',
                5 => 'revision_requested',
                6 => 'rejected',
            ],
        ],

        'construction_survey_visits' => [
            'default' => 2,
            'text_default' => 'checked_in',
            'values' => [
                'draft' => 0,
                'planned' => 1,
                'checked_in' => 2,
                'in_progress' => 2,
                'submitted' => 3,
                'approved' => 4,
                'revision_requested' => 5,
                'rejected' => 6,
            ],
            'reverse' => [
                0 => 'draft',
                1 => 'planned',
                2 => 'checked_in',
                3 => 'submitted',
                4 => 'approved',
                5 => 'revision_requested',
                6 => 'rejected',
            ],
        ],

        'construction_survey_submissions' => [
            'default' => 0,
            'text_default' => 'draft',
            'values' => [
                'draft' => 0,
                'planned' => 1,
                'checked_in' => 2,
                'in_progress' => 2,
                'submitted' => 3,
                'approved' => 4,
                'revision_requested' => 5,
                'rejected' => 6,
            ],
            'reverse' => [
                0 => 'draft',
                1 => 'planned',
                2 => 'in_progress',
                3 => 'submitted',
                4 => 'approved',
                5 => 'revision_requested',
                6 => 'rejected',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => $configuration) {
            $this->assertKnownValues(
                $tableName,
                array_keys($configuration['values'])
            );
        }

        foreach (self::TABLES as $tableName => $configuration) {
           Schema::table($tableName, function (Blueprint $table) use ($configuration) {
    $table->unsignedTinyInteger('status_code')
        ->default($configuration['default']);
});

            foreach ($configuration['values'] as $oldStatus => $newStatus) {
                DB::table($tableName)
                    ->where('status', $oldStatus)
                    ->update([
                        'status_code' => $newStatus,
                    ]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('status');
            });

          DB::statement(sprintf(
    'ALTER TABLE `%s` CHANGE COLUMN `status_code` `status` TINYINT UNSIGNED NOT NULL DEFAULT %d',
    $tableName,
    $configuration['default']
));

            Schema::table($tableName, function (Blueprint $table) {
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName => $configuration) {
            $this->assertKnownValues(
                $tableName,
                array_values($configuration['values'])
            );
        }

        foreach (self::TABLES as $tableName => $configuration) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['status']);
            });

            Schema::table($tableName, function (Blueprint $table) use ($configuration) {
                $table->string('status_text')
                    ->default($configuration['text_default']);
            });

            foreach ($configuration['reverse'] as $oldStatus => $newStatus) {
                DB::table($tableName)
                    ->where('status', $oldStatus)
                    ->update([
                        'status_text' => $newStatus,
                    ]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('status');
            });

            DB::statement(sprintf(
    "ALTER TABLE `%s` CHANGE COLUMN `status_text` `status` VARCHAR(255) NOT NULL DEFAULT '%s'",
    $tableName,
    $configuration['text_default']
));
        }
    }

    /**
     * @param array<int, int|string> $allowedValues
     */
    private function assertKnownValues(
        string $tableName,
        array $allowedValues
    ): void {
        $unknownValues = DB::table($tableName)
            ->whereNotNull('status')
            ->whereNotIn('status', $allowedValues)
            ->distinct()
            ->pluck('status')
            ->map(fn ($status) => (string) $status)
            ->all();

        if ($unknownValues !== []) {
            throw new RuntimeException(sprintf(
                'Cannot convert %s.status because unsupported values exist: %s',
                $tableName,
                implode(', ', $unknownValues)
            ));
        }
    }
};