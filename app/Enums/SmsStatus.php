<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
