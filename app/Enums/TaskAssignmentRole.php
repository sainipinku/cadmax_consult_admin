<?php

namespace App\Enums;

enum TaskAssignmentRole: string
{
    case OWNER = 'owner';
    case EXECUTOR = 'executor';
    case SURVEYOR = 'surveyor';
    case DRAFT_PERSON = 'draft_person';
    case REVIEWER = 'reviewer';
    case CHECKER = 'checker';
    case VERIFIER = 'verifier';
}
