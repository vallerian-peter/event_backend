<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Reserved = 'reserved';
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
