@extends('layouts.landing.app')
@section('title', '{{ $bloc->name }} | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">{{ $bloc->name }}</h1>
            <div class="text">
                {{ $bloc->description }} <br>
                <strong>Member Countries:</strong> {{ $bloc->member_list }} <br>
                <strong>Currencies:</strong> {{ $bloc->currency_list }}
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Trade Corridors Section Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">
            <div class="section-header mb-30 wow fadeInUp">
                <h2 class="title">Trade Corridors within {{ $bloc->name }}</h2>
                <p>Cross-border freight lanes connecting farms and markets across bloc member countries.</p>
            </div>

            @if ($corridors->count() > 0)
            <div class="row g-4">
                @foreach ($corridorsByOrigin as $origin => $originCorridors)
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg--primary text-white">
                            <h5 class="mb-0">{{ $origin }} → Destination Markets</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                @foreach ($originCorridors as $corridor)
                                <li class="mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="fw-bold text-dark">{{ ucfirst($corridor->destination_country) }}</span>
                                        <span class="badge bg--success">{{ $corridor->transit_days_estimate }} days</span>
                                    </div>
                                    <div class="row text-sm g-2">
                                        <div class="col-6">
                                            <span class="text-muted d-block">Distance:</span>
                                            <span>{{ $corridor->distance_km }} km</span>
                                        </div>
                                        <div class="col-6">
                                            <span class="text-muted d-block">Rate/ton:</span>
                                            <span>$ {{ number_format($corridor->trucking_rate_per_ton_usd, 0) }} USD</span>
                                        </div>
                                    </div>
                                    @if ($corridor->cold_chain_available)
                                    <span class="badge bg--info text-white small">❄ Cold Chain</span>
                                    @endif
                                    @if ($corridor->warehousing_available)
                                    <span class="badge bg--warning text-dark small">📦 Warehousing</span>
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <p class="text-muted">No active corridors configured for this bloc yet.</p>
            </div>
            @endif

            <!-- ==== Commodities Section Starts Here ==== -->
            <div class="mt-5">
                <div class="section-header mb-30 wow fadeInUp">
                    <h2 class="title">Trending Commodities in {{ $bloc->name }}</h2>
                    <p>Agricultural products commonly traded across {{ $bloc->name }} member countries.</p>
                </div>
                <div class="row g-4">
                    @foreach ($commodities as $commodity)
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center p-3">
                                <div class="bg--light rounded p-3 mb-3 d-inline-block">
                                    <span class="text-muted">{{ ucfirst($commodity->name ?? 'Commodity') }}</span>
                                </div>
                                <small class="text-muted d-block">{{ $commodity->unit ?? 'kg' }} • Origin: {{ $commodity->origin_farm ?? 'Multiple origins' }}</small>
                                <a href="{{ route('product.details', $commodity->id) }}" class="btn btn--outline-primary mt-2 w-100">
                                    View Listing
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <!-- ==== Commodities Section Ends Here ==== -->
        </div>
    </section>
    <!-- ==== Trade Corridors Section Ends Here ==== -->

    <!-- ==== Customs & Duty Regulations Section Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80" style="background:#f8f9fa;">
        <div class="container max-w1060">
            <div class="section-header mb-30 wow fadeInUp text-center">
                <h2 class="title">Customs & Duty Regulations</h2>
                <p>Import duty, VAT, and required documents for agricultural goods entering {{ $bloc->name }} member countries.</p>
            </div>
            <div class="row g-4">
                @foreach ($memberCountries as $country)
                <?php $countryDuties = $dutyRegulations[$country] ?? collect(); ?>
                @if ($countryDuties->count() > 0)
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg--primary text-white">
                            <h5 class="mb-0">{{ ucfirst($country) }} Import Rules</h5>
                        </div>
                        <div class="card-body p-3">
                            <table class="table table-sm border-0">
                                <tbody>
                                @foreach ($countryDuties as $duty)
                                <tr>
                                    <td class="text-muted small">{{ $duty->hs_description }}</td>
                                    <td class="fw-bold text-end">{{ $duty->duty_rate_percent }}% duty</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">VAT</td>
                                    <td class="fw-bold text-end">{{ $duty->vat_rate_percent }}%</td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="doc-required mt-3">
                                <small class="text-muted d-block">Required Documents:</small>
                                <?php
                                    $docs = [];
                                    foreach ($countryDuties as $d) {
                                        if ($d->requires_sps_certificate) $docs[] = 'SPS Certificate';
                                        if ($d->requires_phytosanitary) $docs[] = 'Phytosanitary Certificate';
                                        if ($d->requires_origin_certificate) $docs[] = 'Origin Certificate';
                                    }
                                    $docs = array_unique($docs);
                                ?>
                                @foreach ($docs as $doc)
                                <span class="badge bg--light text-dark small me-1 mb-1">{{ $doc }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </div>
    </section>
    <!-- ==== Customs & Duty Regulations Section Ends Here ==== -->

@endsection
