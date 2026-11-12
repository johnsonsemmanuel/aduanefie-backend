@extends('layouts.landing.app')
@section('title', 'African Trade Blocs | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">African Trade Blocs</h1>
            <div class="text">
                Trade across ECOWAS, SADC, EAC, and COMESA corridors. Regional agricultural marketplaces for intra-African commerce.
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Trade Blocs Section Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">
            <div class="section-header mb-30 wow fadeInUp text-center">
                <h2 class="title">Regional Trade Blocs</h2>
                <p>Select a trade bloc to browse corridors, commodities, and verified sellers within that region.</p>
            </div>
            <div class="row g-4">
                @foreach ($blocs as $bloc)
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            @if ($bloc->logo)
                            <img src="{{ \App\CentralLogics\Helpers::get_full_url('bloc', $bloc->logo, 'public') }}" width="60" height="60" class="mb-3" alt="{{ $bloc->name }}">
                            @else
                            <div class="bg--primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:60px;height:60px;">
                                <span class="fw-bold">{{ strtoupper(substr($bloc->slug, 0, 2)) }}</span>
                            </div>
                            @endif
                            <h5 class="card-title">{{ $bloc->name }}</h5>
                            <p class="card-text text-muted small">{{ $bloc->description }}</p>
                            <div class="member-countries mt-3">
                                <small class="text-muted d-block">{{ $bloc->member_list }}</small>
                            </div>
                            <div class="currencies mt-2">
                                <span class="badge bg--light text-dark">{{ $bloc->currency_list }}</span>
                            </div>
                            <a href="{{ route('trade-bloc.hub', $bloc->slug) }}" class="btn btn--primary mt-3 w-100">
                                Browse {{ strtoupper($bloc->slug) }} Market
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    <!-- ==== Trade Blocs Section Ends Here ==== -->

@endsection
