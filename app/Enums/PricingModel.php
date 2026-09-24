<?php

namespace App\Enums;

enum PricingModel: string
{
    case Fixed = 'fixed';
    case PerInvitee = 'per_invitee';
}
