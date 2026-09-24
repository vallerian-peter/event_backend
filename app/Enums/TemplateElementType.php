<?php

namespace App\Enums;

enum TemplateElementType: string
{
    case QrCode = 'qr_code';
    case InviteeName = 'invitee_name';
    case CustomText = 'custom_text';
    case TicketTier = 'ticket_tier';
    case SeatNumber = 'seat_number';
}
