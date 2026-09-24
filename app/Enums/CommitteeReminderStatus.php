<?php

namespace App\Enums;

enum CommitteeReminderStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
