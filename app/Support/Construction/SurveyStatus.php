<?php

namespace App\Support\Construction;

final class SurveyStatus
{
    public const DRAFT = 0;

    public const PLANNED = 1;

    public const IN_PROGRESS = 2;

    public const SUBMITTED = 3;

    public const APPROVED = 4;

    public const REVISION_REQUESTED = 5;

    public const REJECTED = 6;

    /**
     * @var array<int, string>
     */
    public const KEYS = [
        self::DRAFT => 'draft',
        self::PLANNED => 'planned',
        self::IN_PROGRESS => 'in_progress',
        self::SUBMITTED => 'submitted',
        self::APPROVED => 'approved',
        self::REVISION_REQUESTED => 'revision_requested',
        self::REJECTED => 'rejected',
    ];

    /**
     * @var array<int, string>
     */
    public const LABELS = [
        self::DRAFT => 'Draft',
        self::PLANNED => 'Planned',
        self::IN_PROGRESS => 'In Progress',
        self::SUBMITTED => 'Submitted',
        self::APPROVED => 'Approved',
        self::REVISION_REQUESTED => 'Revision Requested',
        self::REJECTED => 'Rejected',
    ];

    /**
     * @return array<string, int>
     */
    public static function codes(): array
    {
        return array_flip(self::KEYS);
    }

    public static function key(int $status): string
    {
        return self::KEYS[$status] ?? 'unknown';
    }

    public static function label(int $status): string
    {
        return self::LABELS[$status] ?? 'Unknown';
    }
}