<?php

namespace App\Enums;

enum ClientReviewStatus: string
{
    case PENDING = 'pending';
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REVISION_REQUESTED = 'revision_requested';
    case REJECTED = 'rejected';
}
