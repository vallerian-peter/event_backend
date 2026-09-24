<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case EventOwner = 'event_owner';
    case GateKeeper = 'gate_keeper';
}
