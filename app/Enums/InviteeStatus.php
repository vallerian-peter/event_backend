<?php

namespace App\Enums;

enum InviteeStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Attended = 'attended';
}
