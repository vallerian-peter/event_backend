<?php

namespace App\Enums;

enum CommitteeTitle: string
{
    case Uncle = 'UNCLE';
    case Aunt = 'AUNT';
    case Parent = 'PARENT';
    case Sibling = 'SIBLING';
    case FamilyNeighbor = 'FAMILY_NEIGHBOR';
    case Friend = 'FRIEND';
    case InLaw = 'IN_LAW';
    case Elder = 'ELDER';
    case Other = 'OTHER';
}
