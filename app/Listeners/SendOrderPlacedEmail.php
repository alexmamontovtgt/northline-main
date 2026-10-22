<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderPlacedMail;
use App\Repositories\OrderRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

final class SendOrderPlacedEmail implements ShouldQueue
{
    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function __construct(private readonly OrderRepository $orders) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('customer');

        if ($order->email_sent || $order->customer === null) {
            return;
        }

        Mail::to($order->customer->email)->send(new OrderPlacedMail($order));

        $this->orders->markEmailSent($order);
    }
}
