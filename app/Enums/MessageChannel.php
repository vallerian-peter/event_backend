<?php

namespace App\Enums;

enum MessageChannel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
}
