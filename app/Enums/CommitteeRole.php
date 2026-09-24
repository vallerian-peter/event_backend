<?php

namespace App\Enums;

enum CommitteeRole: string
{
    case CookingFood = 'COOKING_FOOD';
    case Drinks = 'DRINKS';
    case Decoration = 'DECORATION';
    case Logistics = 'LOGISTICS';
    case Finance = 'FINANCE';
    case Transport = 'TRANSPORT';
    case Protocol = 'PROTOCOL';
    case Media = 'MEDIA';
    case Ushering = 'USHERING';
    case Security = 'SECURITY';
    case Other = 'OTHER';
}
