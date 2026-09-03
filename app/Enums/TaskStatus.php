<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PLANNED = 'planned';
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case COMPLETED = 'completed';
    case BLOCKED = 'blocked';
    case CANCELLED = 'cancelled';

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::PLANNED => in_array($to, [self::PENDING, self::IN_PROGRESS, self::CANCELLED], true),
            self::PENDING => in_array($to, [self::IN_PROGRESS, self::BLOCKED, self::CANCELLED], true),
            self::IN_PROGRESS => in_array($to, [self::PENDING, self::REVIEW, self::COMPLETED, self::BLOCKED, self::CANCELLED], true),
            self::REVIEW => in_array($to, [self::IN_PROGRESS, self::COMPLETED, self::BLOCKED, self::CANCELLED], true),
            self::COMPLETED => in_array($to, [self::IN_PROGRESS, self::REVIEW, self::CANCELLED], true),
            self::BLOCKED => in_array($to, [self::PENDING, self::IN_PROGRESS, self::CANCELLED], true),
            self::CANCELLED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::REVIEW => 'Under Review',
            self::COMPLETED => 'Completed',
            self::BLOCKED => 'Blocked',
            self::CANCELLED => 'Cancelled',
        };
    }
}
