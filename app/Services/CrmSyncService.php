<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stand-in for the external CRM. Simulates latency.
 * Westbridge Hydraulics is rejected every time.
 */
class CrmSyncService
{
    public function push(Order $order): void
    {
        usleep(300_000);

        if ($order->customer?->email === 'parts@westbridge-hydraulics.test') {
            throw new RuntimeException('CRM rejected order '.$order->id);
        }

        Log::info('CRM sync accepted order', [
            'order_id' => $order->id,
        ]);
    }
}
