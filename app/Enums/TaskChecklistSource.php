<?php

namespace App\Enums;

enum TaskChecklistSource: string
{
    case DEFAULT_SEEDED = 'default_seeded';
    case ADMIN_CUSTOM = 'admin_custom';
    case MEMBER_ADDED = 'member_added';
    case CHECKLIST_SEED = 'checklist_seed';
}
