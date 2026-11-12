@extends('rider.layout')

@section('title', 'My orders')

@section('topbar')
    <div>
        {{ trim(($rider->f_name ?? '') . ' ' . ($rider->l_name ?? '')) ?: 'Rider' }}
        <form method="post" action="{{ route('rider.logout') }}" style="display:inline;margin-left:8px;">
            @csrf
            <button type="submit" class="link">Sign out</button>
        </form>
    </div>
@endsection

@section('content')
    <nav class="tabs">
        <a href="{{ route('rider.dashboard') }}" class="active">Orders</a>
        <a href="{{ route('rider.earnings') }}">Earnings</a>
    </nav>

    @php
        $statusLabel = fn ($status) => ucfirst(str_replace('_', ' ', (string) $status));
        // Order::getCreatedAtAttribute() returns a formatted string, not Carbon,
        // while updated_at is a real datetime cast. Parse instead of ->format().
        $stamp = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y, H:i') : '—';
    @endphp

    <h2 style="margin-top:0;">Current orders ({{ $currentOrders->count() }})</h2>

    <div class="card">
        @if($currentOrders->isEmpty())
            <p class="empty" style="margin:0;">No orders are assigned to you right now.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Pickup / drop</th>
                        <th>Status</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($currentOrders as $order)
                    <tr>
                        <td>
                            <strong>#{{ $order->id }}</strong><br>
                            <span class="small muted">{{ $stamp($order->created_at) }}</span>
                        </td>
                        <td>
                            @if($order->store)
                                {{ $order->store->name }}
                            @elseif($order->parcel_category)
                                {{ $order->parcel_category->name }}
                            @else
                                {{ ucfirst($order->order_type ?? 'Order') }}
                            @endif
                            @php($drop = is_array($order->receiver_details) ? ($order->receiver_details['address'] ?? null) : null)
                            @if($drop)
                                <br><span class="small muted">{{ $drop }}</span>
                            @endif
                        </td>
                        <td><span class="pill live">{{ $statusLabel($order->order_status) }}</span></td>
                        <td style="text-align:right;">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="alert info">
        Status updates are not available on the web portal yet. Use the Aduanefie rider app
        to confirm pickup and complete deliveries &mdash; delivery completion is verified with a
        one-time code, so it is intentionally kept in the app.
    </div>

    <h2>Recently completed</h2>

    <div class="card">
        @if($recentOrders->isEmpty())
            <p class="empty" style="margin:0;">Nothing here yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Pickup / drop</th>
                        <th>Status</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentOrders as $order)
                    <tr>
                        <td>
                            <strong>#{{ $order->id }}</strong><br>
                            <span class="small muted">{{ $stamp($order->updated_at) }}</span>
                        </td>
                        <td>
                            @if($order->store)
                                {{ $order->store->name }}
                            @elseif($order->parcel_category)
                                {{ $order->parcel_category->name }}
                            @else
                                {{ ucfirst($order->order_type ?? 'Order') }}
                            @endif
                        </td>
                        <td><span class="pill done">{{ $statusLabel($order->order_status) }}</span></td>
                        <td style="text-align:right;">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
