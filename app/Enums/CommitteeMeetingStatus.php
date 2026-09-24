<?php

namespace App\Enums;

enum CommitteeMeetingStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
