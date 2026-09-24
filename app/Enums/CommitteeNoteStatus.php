<?php

namespace App\Enums;

enum CommitteeNoteStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
