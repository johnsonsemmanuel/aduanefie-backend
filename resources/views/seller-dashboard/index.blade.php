@extends('layouts.landing.app')
@section('title', 'Seller Dashboard | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">{{ translate('messages.seller_dashboard') }}</h1>
            <div class="text">
                Manage your RFQs, quotes, and trade contracts.
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Seller Dashboard Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">

            <!-- Store Status -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body p-3">
                            @if ($verificationBadge)
                            <span class="badge {{ $verificationBadge['class'] }} mb-2">{{ $verificationBadge['label'] }}</span>
                            @endif
                            @if ($goldBadge)
                            <span class="badge {{ $goldBadge['class'] }} mb-2">{{ $goldBadge['label'] }}</span>
                            @endif
                            <h6 class="mb-0">{{ $store->name ?? 'My Store' }}</h6>
                            <p class="text-muted small">{{ ucfirst($store->zone?->name ?? 'Location not set') }}</p>
                        </div>
                    </div>
                </div>

                @if (!$isGoldSupplier)
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm text-center bg--light">
                        <div class="card-body p-3">
                            <span class="badge bg--warning text-dark mb-2">{{ translate('messages.upgrade_to_gold') }}</span>
                            <h6 class="mb-3">{{ translate('messages.gold_supplier_benefits') }}</h6>
                            <form action="{{ route('seller.upgrade-gold') }}" method="POST" class="d-inline">
                                @csrf
                                <select name="duration_months" class="form-select form-select-sm mb-2">
                                    <option value="1">{{ translate('messages.one_month') }}</option>
                                    <option value="3">{{ translate('messages.three_months') }}</option>
                                    <option value="6">{{ translate('messages.six_months') }}</option>
                                    <option value="12">{{ translate('messages.twelve_months') }}</option>
                                </select>
                                <button type="submit" class="btn btn--primary w-100 btn-sm">{{ translate('messages.upgrade_now') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

                <div class="col-lg-{{ !$isGoldSupplier ? '4' : '12' }} col-md-6">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body p-3">
                            <span class="badge bg--info text-white mb-2">{{ translate('messages.active_quotes') }}</span>
                            <h6 class="mb-0">{{ $activeQuotes->count() }}</h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Received RFQs -->
            <div class="mb-4">
                <h2 class="title">{{ translate('messages.received_rfqs') }}</h2>
                @if ($receivedRfqs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg--primary text-white">
                        <tr>
                            <th>{{ translate('messages.product') }}</th>
                            <th>{{ translate('messages.quantity') }}</th>
                            <th>{{ translate('messages.destination') }}</th>
                            <th>{{ translate('messages.incoterm') }}</th>
                            <th class="text-end">{{ translate('messages.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($receivedRfqs as $rfq)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ ucfirst($rfq->product->name ?? 'Commodity') }}</div>
                                <small class="text-muted">{{ $rfq->origin_country ?? 'Unknown' }} → {{ $rfq->destination_country }}</small>
                            </td>
                            <td>{{ $rfq->quantity }} {{ $rfq->unit }}</td>
                            <td>{{ ucfirst($rfq->destination_country) }}</td>
                            <td>{{ $rfq->incoterm }}</td>
                            <td class="text-end">
                                <button class="btn btn--primary btn-sm" data-bs-toggle="modal" data-bs-target="#rfqModal_{{ $rfq->id }}">
                                    {{ translate('messages.submit_quote') }}
                                </button>
                            </td>
                        </tr>

                        <!-- Quote Modal -->
                        <div class="modal fade" id="rfqModal_{{ $rfq->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ translate('messages.submit_quote_for') }}: {{ ucfirst($rfq->product->name ?? 'Product') }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="{{ route('seller.rfq.respond', $rfq->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">{{ translate('messages.quoted_price') }}</label>
                                                <input type="number" name="quoted_price" class="form-control" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ translate('messages.currency') }}</label>
                                                <select name="currency_code" class="form-select">
                                                    <option value="GHS">GHS</option>
                                                    <option value="NGN">NGN</option>
                                                    <option value="KES">KES</option>
                                                    <option value="USD">USD</option>
                                                    <option value="XOF">XOF</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ translate('messages.estimated_delivery') }}</label>
                                                <input type="date" name="estimated_delivery" class="form-control">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">{{ translate('messages.terms') }}</label>
                                                <textarea name="terms" class="form-control" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn--secondary" data-bs-dismiss="modal">{{ translate('messages.cancel') }}</button>
                                            <button type="submit" class="btn btn--primary">{{ translate('messages.submit_quote') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <p class="text-muted">{{ translate('messages.no_received_rfqs') }}</p>
                </div>
                @endif
            </div>

            <!-- Active Quotes -->
            <div class="mb-4">
                <h2 class="title">{{ translate('messages.active_quotes_title') }}</h2>
                @if ($activeQuotes->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg--primary text-white">
                        <tr>
                            <th>{{ translate('messages.product') }}</th>
                            <th>{{ translate('messages.buyer') }}</th>
                            <th>{{ translate('messages.quoted_price') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($activeQuotes as $quote)
                        <tr>
                            <td>{{ ucfirst($quote->tradeRequest->product->name ?? 'Commodity') }}</td>
                            <td>Buyer #{{ $quote->tradeRequest->buyer_id }}</td>
                            <td>{{ \App\CentralLogics\Helpers::format_currency($quote->quoted_price) }} {{ $quote->currency_code }}</td>
                            <td>
                                <span class="badge bg--warning text-dark">{{ translate('messages.pending') }}</span>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <p class="text-muted">{{ translate('messages.no_active_quotes') }}</p>
                </div>
                @endif
            </div>

            <!-- Active Contracts -->
            <div class="mb-4">
                <h2 class="title">{{ translate('messages.active_contracts') }}</h2>
                @if ($activeContracts->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="bg--primary text-white">
                        <tr>
                            <th>{{ translate('messages.contract_id') }}</th>
                            <th>{{ translate('messages.product') }}</th>
                            <th>{{ translate('messages.amount') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-end">{{ translate('messages.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($activeContracts as $contract)
                        <tr>
                            <td>#{{ $contract->id }}</td>
                            <td>{{ ucfirst($contract->tradeRequest->product->name ?? 'Commodity') }}</td>
                            <td>{{ \App\CentralLogics\Helpers::format_currency($contract->contract_amount) }} {{ $contract->currency_code }}</td>
                            <td>
                                <span class="badge @if($contract->payment_status == 'deposited') bg--success text-white @else bg--primary text-white @endif">
                                    {{ $contract->getStatusLabel() }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('trade-contracts.view', $contract->id) }}" class="btn btn--outline-primary btn-sm">
                                    {{ translate('messages.view') }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <p class="text-muted">{{ translate('messages.no_active_contracts') }}</p>
                </div>
                @endif
            </div>

        </div>
    </section>
    <!-- ==== Seller Dashboard Ends Here ==== -->

@endsection
