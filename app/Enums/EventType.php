<?php

namespace App\Enums;

enum EventType: string
{
    case Conference = 'conference';
    case Wedding = 'wedding';
    case Corporate = 'corporate';
    case Social = 'social';
    case Workshop = 'workshop';
    case BirthDay = 'birthDay';
    case KitchenParty = 'kitchenParty';
    case Confirmation = 'confirmation';
    case Graduation = 'graduation';
    case SendOff = 'sendOff';
    case Communions = 'communions';
    case BurialCeremony = 'burialCeremony';
    case FortiethCeremony = 'fortiethCeremony';
    case Cinema = 'cinema';
    case Club = 'club';
    case Concert = 'concert';
    case Sports = 'sports';
}
