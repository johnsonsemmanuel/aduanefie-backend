@extends('layouts.landing.app')
@section('title', 'Agricultural Marketplace | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">Agricultural Marketplace</h1>
            <div class="text">
                Discover African-grown commodities and request quotes from verified sellers across borders.
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Market Filters Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">
            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.category') }}</label>
                        <select name="category" id="category_filter" class="form-control">
                            <option value="">{{ translate('messages.all_categories') }}</option>
                            @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ ucfirst($category->name ? $category->name : 'Category') }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.search') }}</label>
                        <input type="text" name="search" id="search_filter" class="form-control"
                               placeholder="{{ translate('messages.search_commodities') }}"
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 d-flex align-items-end">
                    <button class="btn btn--primary w-100" id="apply_filters">{{ translate('messages.apply_filters') }}</button>
                </div>
            </div>

            <!-- ==== Commodity Grid Starts Here ==== -->
            <div class="row g-4">
                @if ($commodities->count() > 0)
                @foreach ($commodities as $commodity)
                <div class="col-lg-3 col-md-6">
                    <div class="card h-100 border-0 shadow-sm hover-card">
                        <div class="card-body p-3">
                            <div class="product-image mb-3">
                                <img src="{{ $commodity->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                     class="img-fluid w-100" height="200" style="object-fit:cover;"
                                     alt="{{ $commodity->name }}">
                            </div>
                            <h5 class="card-title mb-2">{{ ucfirst($commodity->name ?? 'Commodity') }}</h5>
                            <span class="badge bg--light text-dark small mb-2 d-block">
                                {{ $commodity->unit ?? 'kg' }} • {{ ucfirst($commodity->origin_farm ?? 'Origin: Multiple') }}
                            </span>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold text-dark">{{ \App\CentralLogics\Helpers::format_currency($commodity->price) }}</span>
                                <span class="text-muted small">per {{ $commodity->unit ?? 'kg' }}</span>
                            </div>
                            <a href="{{ route('market.show', $commodity->id) }}" class="btn btn--outline-primary w-100">
                                {{ translate('messages.view_details') }}
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
                @else
                <div class="col-12 text-center py-5">
                    <p class="text-muted">{{ translate('messages.no_commodities_found') }}</p>
                </div>
                @endif
            </div>
            <!-- ==== Commodity Grid Ends Here ==== -->

            <!-- ==== Pagination Starts Here ==== -->
            <div class="mt-4">
                {{ $commodities->links() }}
            </div>
            <!-- ==== Pagination Ends Here ==== -->
        </div>
    </section>
    <!-- ==== Market Filters Ends Here ==== -->

@endsection

@push('css_or_js')
<script>
    document.getElementById('apply_filters')?.addEventListener('click', function() {
        const category = document.getElementById('category_filter').value;
        const search = document.getElementById('search_filter').value;
        const url = new URL(window.location);
        if (category) url.searchParams.set('category', category);
        else url.searchParams.delete('category');
        if (search) url.searchParams.set('search', search);
        else url.searchParams.delete('search');
        window.location.href = url.toString();
    });
</script>
@endpush
