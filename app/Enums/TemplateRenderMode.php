<?php

namespace App\Enums;

enum TemplateRenderMode: string
{
    case QrOnly = 'qr_only';
    case QrAndName = 'qr_and_name';
    case Custom = 'custom';
}
