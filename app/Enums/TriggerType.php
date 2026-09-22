<?php

namespace App\Enums;

enum TriggerType: string
{
    case Comment = 'comment';
    case Message = 'message';
}
