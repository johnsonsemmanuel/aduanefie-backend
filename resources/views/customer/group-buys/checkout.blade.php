@extends('layouts.app')

@section('title', 'Checkout - Group Buy #' . $group->id)

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-shopping-cart"></i></span>
                <span>Checkout Your Share</span>
            </h1>
            <p class="text-muted mb-0">
                Group Buy #{{ $group->id }} &middot; {{ $group->item?->name }}
            </p>
        </div>
        <a href="{{ route('customer.group-buys.show', $group->id) }}" class="btn btn-outline-secondary btn-sm">Back to group</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Order Summary</h5></div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Item:</strong></td>
                            <td class="text-right">{{ $group->item?->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Store:</strong></td>
                            <td class="text-right">{{ $group->store?->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Units:</strong></td>
                            <td class="text-right">{{ $participant->share_units }}</td>
                        </tr>
                        <tr>
                            <td><strong>Unit price:</strong></td>
                            <td class="text-right">GH₵ {{ number_format($unitPrice, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Delivery charge:</strong></td>
                            <td class="text-right">GH₵ {{ number_format($participant->delivery_charge_ghs, 2) }}</td>
                        </tr>
                        <tr class="font-weight-bold">
                            <td><strong>Your total:</strong></td>
                            <td class="text-right">GH₵ {{ number_format($participant->item_share_ghs + $participant->delivery_charge_ghs, 2) }}</td>
                        </tr>
                    </table>

                    @if ($participant->delivery_address)
                        <div class="alert alert-info mt-3">
                            <h6>Delivery Address</h6>
                            <p class="mb-0">
                                {{ $participant->delivery_address['street'] ?? '' }},<br>
                                {{ $participant->delivery_address['city'] ?? '' }}, {{ $participant->delivery_address['country'] ?? '' }}
                            </p>
                            @if ($participant->gpsLink())
                                <a href="{{ $participant->gpsLink() }}" target="_blank" class="btn btn-outline-primary btn-sm mt-2">Track on Google Maps</a>
                            @endif
                        </div>
                    @endif

                    <hr>
                    <div class="alert alert-warning">
                        <strong>Important:</strong> Your payment is held in escrow until every member of this group has paid. Once all {{ $group->member_limit }} members have checked out, funds are released to the store and your items ship.
                    </div>

                    <form action="{{ route('web.payment', ['payment_method' => 'paystack']) }}" method="GET">
                        <input type="hidden" name="group_buy_id" value="{{ $group->id }}">
                        <input type="hidden" name="amount" value="{{ $participant->item_share_ghs + $participant->delivery_charge_ghs }}">
                        <button type="submit" class="btn btn-primary btn-lg btn-block">Pay GH₵ {{ number_format($participant->item_share_ghs + $participant->delivery_charge_ghs, 2) }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4 sticky-top" style="top: 1rem;">
                <div class="card-body">
                    <h6 class="mb-3">Group Progress</h6>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: {{ (($group->paidCount() / $group->member_limit) * 100) }}%"></div>
                    </div>
                    <p class="mb-1"><strong>{{ $group->paidCount() }} of {{ $group->member_limit }}</strong> members paid</p>
                    <p class="mb-1"><strong>{{ $group->unpaidCount() }}</strong> still owe payment</p>
                    @if ($group->unpaidCount() > 0)
                        <div class="alert alert-info small mt-2">
                            Reminders will be sent to unpaid members 24h before the group closes on {{ $group->closes_at?->format('M d, H:i') }}.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
