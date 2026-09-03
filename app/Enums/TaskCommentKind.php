<?php

namespace App\Enums;

enum TaskCommentKind: string
{
    case COMMENT = 'comment';
    case STATUS_NOTE = 'status_note';
    case PROGRESS_NOTE = 'progress_note';
    case PROOF_NOTE = 'proof_note';
    case CHECKLIST_NOTE = 'checklist_note';
    case SYSTEM_NOTE = 'system_note';
}
