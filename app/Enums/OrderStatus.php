<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
