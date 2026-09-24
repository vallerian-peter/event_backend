<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Valid = 'valid';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Transferred = 'transferred';
}
