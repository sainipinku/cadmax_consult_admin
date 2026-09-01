<?php

namespace App\Enums;

enum TaskSource: string
{
    case ADMIN_CREATED = 'admin_created';
    case MEMBER_MANUAL = 'member_manual';
    case EXECUTION_PLAN_SEED = 'execution_plan_seed';
    case SURVEY_CHECKLIST_SEED = 'survey_checklist_seed';
}
