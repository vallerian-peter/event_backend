<?php

namespace App\Enums;

enum CheckInStatus: string
{
    case Success = 'success';
    case Duplicate = 'duplicate';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Denied = 'denied';
}
