<?php

namespace App\Enums;

enum LicenseKeyStatus: string
{
    case Available = 'available';
    case Used = 'used';
    case Expired = 'expired';
}
