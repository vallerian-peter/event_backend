<?php

namespace App\Enums;

enum PurchaseType: string
{
    case Initial = 'initial';
    case Upgrade = 'upgrade';
    case Addon = 'addon';
}
