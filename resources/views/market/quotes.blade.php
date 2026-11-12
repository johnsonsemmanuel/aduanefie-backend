@extends('layouts.landing.app')
@section('title', 'Quotes | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">{{ translate('messages.received_quotes') }}</h1>
            <div class="text">
                Review and accept quotes for your trade request.
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Quotes Section Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="title mb-0">{{ translate('messages.quotes_for') }}: {{ ucfirst($tradeRequest->product->name ?? 'Commodity') }}</h2>
                    <p class="text-muted small">
                        {{ $tradeRequest->quantity }} {{ $tradeRequest->unit }} → {{ ucfirst($tradeRequest->destination_country) }} • {{ $tradeRequest->incoterm }}
                    </p>
                </div>
                <a href="{{ route('market.my-requests') }}" class="btn btn--primary">{{ translate('messages.back_to_requests') }}</a>
            </div>

            @if ($tradeRequest->quotes->count() > 0)
            <div class="row g-4">
                @foreach ($tradeRequest->quotes as $quote)
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm {{ $quote->status == 'accepted' ? 'border-success' : '' }}">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">{{ ucfirst($quote->seller->name ?? 'Seller') }}</h5>
                                    <span class="badge @if($quote->status == 'accepted') bg-success @else bg-light text-dark @endif">
                                        {{ ucfirst($quote->status) }}
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-dark">{{ \App\CentralLogics\Helpers::format_currency($quote->quoted_price) }} {{ $quote->currency_code }}</span>
                                </div>
                            </div>

                            @if ($quote->estimated_delivery)
                            <div class="mb-3">
                                <span class="text-muted small d-block">{{ translate('messages.estimated_delivery') }}</span>
                                <span class="fw-bold">{{ $quote->estimated_delivery }}</span>
                            </div>
                            @endif

                            @if ($quote->terms)
                            <div class="mb-3">
                                <span class="text-muted small d-block">{{ translate('messages.terms') }}</span>
                                <p class="small">{{ $quote->terms }}</p>
                            </div>
                            @endif

                            @if ($quote->status == 'pending')
                            <form action="{{ route('market.quotes.accept', $quote->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn--primary btn-sm"
                                        onclick="return confirm('{{ translate('messages.accept_quote_confirm') }}')">
                                    {{ translate('messages.accept_quote') }}
                                </button>
                            </form>
                            @elseif ($quote->status == 'accepted')
                            <span class="badge bg-success text-white">{{ translate('messages.accepted') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <p class="text-muted">{{ translate('messages.no_quotes_yet') }}</p>
            </div>
            @endif

            <!-- Trade Estimates -->
            @if (session('freight_estimate') || session('duty_estimate'))
            <div class="mt-5">
                <h6 class="mb-3">{{ translate('messages.cross_border_estimates') }}</h6>
                <div class="row g-3">
                    @if (session('freight_estimate'))
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm p-3">
                            <h6 class="mb-2">{{ translate('messages.freight_estimate') }}</h6>
                            <p class="text-muted small">{{ session('freight_estimate')['corridor'] ?? '' }}</p>
                            <p class="fw-bold">${{ number_format(session('freight_estimate')['estimated_cost_usd'] ?? 0, 2) }} USD</p>
                            <small class="text-muted">{{ session('freight_estimate')['transit_days'] ?? '' }} {{ translate('messages.transit_days') }}</small>
                        </div>
                    </div>
                    @endif
                    @if (session('duty_estimate'))
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm p-3">
                            <h6 class="mb-2">{{ translate('messages.duty_estimate') }}</h6>
                            <p class="fw-bold">${{ number_format(session('duty_estimate')['total_usd'] ?? 0, 2) }} USD</p>
                            <small class="text-muted">Duty: {{ session('duty_estimate')['duty_rate'] ?? 0 }}% • VAT: {{ session('duty_estimate')['vat_rate'] ?? 0 }}%</small>
                        </div>
                    </div>
                    @endif
                </div>
                @if (session('required_documents') && count(session('required_documents')) > 0)
                <div class="mt-3">
                    <span class="text-muted small d-block">{{ translate('messages.required_documents') }}:</span>
                    @foreach (session('required_documents') as $doc)
                    <span class="badge bg--light text-dark small me-1 mb-1">{{ $doc }}</span>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        </div>
    </section>
    <!-- ==== Quotes Section Ends Here ==== -->

@endsection
