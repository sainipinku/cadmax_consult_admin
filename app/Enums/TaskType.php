<?php

namespace App\Enums;

/**
 * Unified task lifecycle classifier.
 * Migration default is 'one_time' (2025_06_30_125039_add_task_type_and_recurring_columns_to_tasks_table).
 * Task scopes and instance generators rely on literal values 'recurring' and 'one_time'.
 * New task types may be appended here; keep values stable because they are persisted to the DB.
 */
enum TaskType: string
{
    case ONE_TIME = 'one_time';
    case RECURRING = 'recurring';
    case MILESTONE = 'milestone';
    case STAGE = 'stage';

    public function label(): string
    {
        return match ($this) {
            self::ONE_TIME => 'One time',
            self::RECURRING => 'Recurring',
            self::MILESTONE => 'Milestone',
            self::STAGE => 'Stage',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::ONE_TIME => 0,
            self::RECURRING => 1,
            self::MILESTONE => 2,
            self::STAGE => 3,
        };
    }
}
