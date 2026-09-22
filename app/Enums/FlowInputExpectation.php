<?php

namespace App\Enums;

enum FlowInputExpectation: string
{
    case Text = 'text';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
}
