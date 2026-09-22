<?php

namespace App\Enums;

enum DataDeletionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
