@extends('layouts.landing.app')
@section('title', 'My Trade Requests | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">{{ translate('messages.my_trade_requests') }}</h1>
            <div class="text">
                Track your RFQs, quotes, and active trade contracts.
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Trade Requests Table Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">
            @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-4">
                {{ session('success') }}
                <button type="button" class="btn-close" data-dismiss="alert"></button>
            </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="title mb-0">{{ translate('messages.your_requests') }}</h2>
                <a href="{{ route('market.index') }}" class="btn btn--primary">
                    {{ translate('messages.browse_commodities') }}
                </a>
            </div>

            @if ($requests->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="bg--primary text-white">
                    <tr>
                        <th>{{ translate('messages.product') }}</th>
                        <th>{{ translate('messages.quantity') }}</th>
                        <th>{{ translate('messages.destination') }}</th>
                        <th>{{ translate('messages.incoterm') }}</th>
                        <th>{{ translate('messages.status') }}</th>
                        <th class="text-end">{{ translate('messages.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($requests as $req)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="fw-bold">{{ ucfirst($req->product->name ?? 'Commodity') }}</div>
                                <small class="text-muted d-block">{{ $req->origin_country ?? 'Unknown' }} → {{ $req->destination_country }}</small>
                            </div>
                        </td>
                        <td>{{ $req->quantity }} {{ $req->unit }}</td>
                        <td>{{ ucfirst($req->destination_country) }}</td>
                        <td>{{ $req->incoterm }}</td>
                        <td>
                            <span class="badge @if($req->status == 'open') bg-warning @elseif($req->status == 'quoted') bg-info @elseif($req->status == 'accepted') bg-success @elseif($req->status == 'rejected') bg-danger @else bg-secondary @endif">
                                {{ ucfirst(str_replace('_', ' ', $req->status)) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if ($req->status == 'open')
                            <a href="{{ route('market.show', $req->product_id) }}" class="btn btn--outline-primary btn-sm">
                                {{ translate('messages.view') }}
                            </a>
                            @elseif ($req->status == 'quoted')
                            <a href="{{ route('market.quotes', $req->id) }}" class="btn btn--primary btn-sm">
                                {{ translate('messages.view_quotes') }}
                            </a>
                            @elseif ($req->status == 'accepted')
                            <a href="{{ route('trade-contracts.view', $req->contract?->id ?? 0) }}" class="btn btn--success btn-sm">
                                {{ translate('messages.view_contract') }}
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $requests->links() }}
            </div>
            @else
            <div class="text-center py-5">
                <p class="text-muted">{{ translate('messages.no_trade_requests') }}</p>
                <a href="{{ route('market.index') }}" class="btn btn--primary">{{ translate('messages.browse_commodities') }}</a>
            </div>
            @endif
        </div>
    </section>
    <!-- ==== Trade Requests Table Ends Here ==== -->

@endsection
