<?php

namespace App\Enums;

enum TaskAssignmentStatus: string
{
    case PENDING_ACCEPTANCE = 'pending_acceptance';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case REVOKED = 'revoked';
    case REJECTED = 'rejected';
}
