Order {{ $order->id }} for {{ $order->customer->name }} was received.
Total: {{ $order->total_amount }}

@foreach ($order->items as $item)
- {{ $item->qty }} x {{ $item->product_name }} @ {{ $item->unit_price }}
@endforeach
