<?php

namespace App\Enums;

/**
 * Rhythm for TaskType::RECURRING tasks.
 * Values are string literals compared directly in the task instance generators
 * (DailyInstanceGenerator, WeeklyInstanceGenerator, MonthlyInstanceGenerator, etc.)
 * and against routes/console schedule commands (daily, weekly, monthly, quarterly,
 * half-yearly, yearly). Keep values exactly as they are persisted in the DB.
 */
enum RecurringType: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half_yearly';
    case YEARLY = 'yearly';
    case SPECIFIC_DAY = 'specific_day';
    case SPECIFIC_DATE = 'specific_date';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::HALF_YEARLY => 'Half-yearly',
            self::YEARLY => 'Yearly',
            self::SPECIFIC_DAY => 'Specific day(s) of the week',
            self::SPECIFIC_DATE => 'Specific date of the month',
        };
    }
}
