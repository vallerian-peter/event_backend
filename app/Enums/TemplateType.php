<?php

namespace App\Enums;

enum TemplateType: string
{
    case InvitationCard = 'invitation_card';
    case TicketPass = 'ticket_pass';
    case ReminderCard = 'reminder_card';
}
