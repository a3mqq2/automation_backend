<?php

namespace App\Enums;

enum FlowIssueSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
