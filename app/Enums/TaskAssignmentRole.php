<?php

namespace App\Enums;

enum TaskAssignmentRole: string
{
    case OWNER = 'owner';
    case EXECUTOR = 'executor';
    case REVIEWER = 'reviewer';
    case CHECKER = 'checker';
    case VERIFIER = 'verifier';
}
