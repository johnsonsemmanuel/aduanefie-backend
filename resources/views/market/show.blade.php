@extends('layouts.landing.app')
@section('title', '{{ $product->name }} | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">{{ ucfirst($product->name ?? 'Product') }}</h1>
            <div class="text">
                {{ ucfirst($product->description ?? 'Premium agricultural commodity available for cross-border trade.') }}
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Product Detail Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">
            <div class="row g-4">
                <!-- Product Image -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <img src="{{ $product->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                 class="img-fluid w-100" height="400" style="object-fit:cover;"
                                 alt="{{ $product->name }}">
                        </div>
                    </div>
                </div>

                <!-- Product Info & RFQ Form -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="mb-3">{{ ucfirst($product->name ?? 'Product') }}</h3>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <span class="text-muted small d-block">{{ translate('messages.origin') }}</span>
                                    <span class="fw-bold">{{ ucfirst($product->origin_farm ?? 'Multiple origins') }}</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">{{ translate('messages.unit') }}</span>
                                    <span class="fw-bold">{{ $product->unit ?? 'kg' }}</span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <span class="text-muted small d-block">{{ translate('messages.price') }}</span>
                                    <span class="fw-bold text-dark">{{ \App\CentralLogics\Helpers::format_currency($product->price) }}</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">{{ translate('messages.min_order_qty') }}</span>
                                    <span class="fw-bold">{{ $product->minimum_order_qty ?? 1 }} {{ $product->unit ?? 'kg' }}</span>
                                </div>
                            </div>

                            @if ($product->certification)
                            <div class="mb-3">
                                <span class="text-muted small d-block">{{ translate('messages.certification') }}</span>
                                <span class="badge bg--success text-white">{{ $product->certification }}</span>
                            </div>
                            @endif

                            @if ($product->hs_code)
                            <div class="mb-3">
                                <span class="text-muted small d-block">{{ translate('messages.hs_code') }}</span>
                                <span class="fw-bold">{{ $product->hs_code }} - {{ $product->hs_description }}</span>
                            </div>
                            @endif

                            @if ($product->cold_chain_required)
                            <span class="badge bg--info text-white small mb-2 d-inline-block">❄ {{ translate('messages.cold_chain_required') }}</span>
                            @endif

                            <hr class="my-4">

                            <!-- Bulk Pricing -->
                            @if ($product->bulkPricingTiers->count() > 0)
                            <div class="mb-4">
                                <h6 class="mb-2">{{ translate('messages.bulk_pricing') }}</h6>
                                <table class="table table-sm border-0">
                                    <tbody>
                                    @foreach ($product->bulkPricingTiers as $tier)
                                    <tr>
                                        <td class="text-muted small">{{ $tier->min_quantity }}+ {{ $product->unit ?? 'kg' }}</td>
                                        <td class="fw-bold text-end">{{ \App\CentralLogics\Helpers::format_currency($tier->price_override ?? $product->price * (1 - $tier->discount_percent / 100)) }}</td>
                                        @if ($tier->discount_percent > 0)
                                        <td class="text-success small">{{ $tier->discount_percent }}% off</td>
                                        @endif
                                    </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            <!-- RFQ Form -->
                            @if (auth('customer')->check() || auth('vendor')->check())
                            <div class="bg--light rounded p-4 mb-4">
                                <h6 class="mb-3">{{ translate('messages.request_quote') }}</h6>
                                <form action="{{ route('market.request-quote') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('messages.quantity') }}</label>
                                            <input type="number" name="quantity" class="form-control"
                                                   value="{{ $product->minimum_order_qty ?? 1 }}" min="1" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('messages.unit') }}</label>
                                            <input type="text" name="unit" class="form-control"
                                                   value="{{ $product->unit ?? 'kg' }}" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('messages.destination_country') }}</label>
                                            <input type="text" name="destination_country" class="form-control"
                                                   placeholder="{{ translate('messages.enter_destination') }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('messages.incoterm') }}</label>
                                            <select name="incoterm" class="form-select">
                                                <option value="EXW">EXW - Ex Works</option>
                                                <option value="FOB">FOB - Free On Board</option>
                                                <option value="CIF">CIF - Cost Insurance Freight</option>
                                                <option value="DAP">DAP - Delivered At Place</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="input-label">{{ translate('messages.needed_by') }}</label>
                                            <input type="date" name="needed_by" class="form-control">
                                        </div>
                                        <div class="col-12">
                                            <label class="input-label">{{ translate('messages.notes') }}</label>
                                            <textarea name="notes" class="form-control" rows="2"
                                                      placeholder="{{ translate('messages.add_notes') }}"></textarea>
                                        </div>
                                        <div class="col-12 d-grid">
                                            <button type="submit" class="btn btn--primary btn-lg">
                                                {{ translate('messages.submit_request') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            @else
                            <div class="alert alert-info text-center">
                                {{ translate('messages.login_to_request') }}
                                <a href="{{ route('login', ['tab' => 'customer']) }}" class="btn btn--primary btn-sm ms-2">
                                    {{ translate('messages.login') }}
                                </a>
                            </div>
                            @endauth

                            <!-- Related Commodities -->
                            @if ($related->count() > 0)
                            <div class="mt-5">
                                <h6 class="mb-3">{{ translate('messages.related_commodities') }}</h6>
                                <div class="row g-3">
                                    @foreach ($related as $relatedItem)
                                    <div class="col-6">
                                        <div class="d-flex align-items-center border-bottom pb-2 mb-2">
                                            <img src="{{ $relatedItem->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                 width="50" height="50" class="rounded me-2" style="object-fit:cover;"
                                                 alt="{{ $relatedItem->name }}">
                                            <div>
                                                <div class="fw-bold small">{{ ucfirst($relatedItem->name ?? 'Commodity') }}</div>
                                                <div class="text-muted smaller">{{ \App\CentralLogics\Helpers::format_currency($relatedItem->price) }} / {{ $relatedItem->unit ?? 'kg' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ==== Product Detail Ends Here ==== -->

@endsection

@push('css_or_js')
<style>
    .hover-card:hover {
        transform: translateY(-5px);
        transition: transform 0.3s ease;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
</style>
@endpush
