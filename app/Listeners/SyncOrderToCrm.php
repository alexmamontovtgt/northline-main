<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\CrmSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncOrderToCrm implements ShouldQueue
{
    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(private readonly CrmSyncService $crm) {}

    public function handle(OrderPlaced $event): void
    {
        $this->crm->push($event->order->loadMissing('customer'));
    }

    public function failed(OrderPlaced $event, Throwable $exception): void
    {
        Log::warning('CRM sync failed', [
            'order_id' => $event->order->id,
            'message' => $exception->getMessage(),
        ]);
    }
}
