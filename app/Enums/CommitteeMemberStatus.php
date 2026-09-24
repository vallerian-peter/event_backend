<?php

namespace App\Enums;

enum CommitteeMemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Removed = 'removed';
}
