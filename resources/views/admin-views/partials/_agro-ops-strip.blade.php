@php
    $opsActiveMarketers = \App\Models\Admin::where('role_id', (int) config('marketer.role_id', 5))->count();
    $opsPendingMarketers = \Illuminate\Support\Facades\Schema::hasTable('marketer_applications')
        ? \App\Models\MarketerApplication::where('status', 'pending')->count() : 0;
    $opsPendingBackyard = \Illuminate\Support\Facades\Schema::hasTable('backyard_farm_applications')
        ? \App\Models\BackyardFarmApplication::where('status', 'pending')->count() : 0;
    $opsUpcomingTours = \Illuminate\Support\Facades\Schema::hasTable('regional_tours')
        ? \App\Models\RegionalTour::whereIn('status', ['upcoming', 'open', 'in_progress'])->count() : 0;
    $opsLiveCrops = \Illuminate\Support\Facades\Schema::hasColumn('items', 'farming_status')
        ? \App\Models\Item::whereIn('farming_status', ['farming', 'harvest_ready'])->count() : 0;
    $opsOpenGroupBuys = \Illuminate\Support\Facades\Schema::hasTable('group_buys')
        ? \Illuminate\Support\Facades\DB::table('group_buys')->where('status', 'open')->count() : 0;
    $opsAgroPrices = \Illuminate\Support\Facades\Schema::hasTable('agro_market_prices')
        ? \Illuminate\Support\Facades\DB::table('agro_market_prices')->where('is_active', true)->count() : 0;
    $opsSmsBalance = \App\Traits\SmsGateway::intekhostBalance();
@endphp

<div class="card mb-3">
    <div class="card-header py-2 border-0">
        <div class="search--button-wrapper">
            <h5 class="card-title d-flex align-items-center mb-0">
                <img src="{{ asset('/public/assets/admin/img/grocery.svg') }}" width="22" class="mr-2" alt="">
                <span>Aduanefie Agribusiness &amp; Field Operations</span>
            </h5>
            <div class="btn--container">
                <a href="{{ route('admin.marketer.index') }}" class="btn btn--secondary">Remote Marketers</a>
                <a href="{{ route('admin.agro-prices.index') }}" class="btn btn--primary">Market Prices ({{ $opsAgroPrices }})</a>
            </div>
        </div>
    </div>
    <div class="card-body pt-1">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-sm-6">
                <a href="{{ route('admin.marketer.index') }}" class="text-decoration-none d-block">
                    <div class="resturant-card card--bg-1">
                        <h4 class="title">{{ $opsActiveMarketers }}</h4>
                        <span class="subtitle">Remote Field Marketers</span>
                        <img class="resturant-icon" src="{{ asset('/public/assets/admin/img/total-store.png') }}" alt="marketers">
                    </div>
                </a>
            </div>
            <div class="col-xl-3 col-sm-6">
                <a href="{{ route('admin.regional-tours.index') }}" class="text-decoration-none d-block">
                    <div class="resturant-card card--bg-2">
                        <h4 class="title">{{ $opsUpcomingTours }}</h4>
                        <span class="subtitle">Active Regional Tours</span>
                        <img class="resturant-icon" src="{{ asset('/public/assets/admin/img/active-store.png') }}" alt="tours">
                    </div>
                </a>
            </div>
            <div class="col-xl-3 col-sm-6">
                <a href="{{ route('admin.backyard-farmers.index') }}" class="text-decoration-none d-block">
                    <div class="resturant-card card--bg-3">
                        <h4 class="title">{{ $opsLiveCrops }}</h4>
                        <span class="subtitle">Live Crops in Cultivation</span>
                        <img class="resturant-icon" src="{{ asset('/public/assets/admin/img/add-store.png') }}" alt="crops">
                    </div>
                </a>
            </div>
            <div class="col-xl-3 col-sm-6">
                <a href="{{ route('admin.business-settings.farming-settings') }}" class="text-decoration-none d-block">
                    <div class="resturant-card card--bg-4">
                        <h4 class="title">{{ isset($opsSmsBalance['units']) ? number_format($opsSmsBalance['units']) : '—' }}</h4>
                        <span class="subtitle">IntekHost SMS Credits</span>
                        <img class="resturant-icon" src="{{ asset('/public/assets/admin/img/add-new.png') }}" alt="sms">
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="{{ route('admin.marketer-applications.index') }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-user-add text-primary mr-2" style="font-size: 1.2rem;"></i>
                            <span>Pending Marketer Apps</span>
                        </h6>
                        <span class="card-title {{ $opsPendingMarketers > 0 ? 'text-warning' : 'text-success' }}">
                            {{ $opsPendingMarketers }}
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="{{ route('admin.backyard-farmers.index') }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-leaf text-success mr-2" style="font-size: 1.2rem;"></i>
                            <span>Pending Backyard Farms</span>
                        </h6>
                        <span class="card-title {{ $opsPendingBackyard > 0 ? 'text-warning' : 'text-success' }}">
                            {{ $opsPendingBackyard }}
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="https://aduanefie.com/group-buys" target="_blank" rel="noopener">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-group-senior text-info mr-2" style="font-size: 1.2rem;"></i>
                            <span>Open Group Buys</span>
                        </h6>
                        <span class="card-title text-3F8CE8">
                            {{ $opsOpenGroupBuys }}
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="https://partners.aduanefie.com/marketer-login" target="_blank" rel="noopener">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-open-in-new text-primary mr-2" style="font-size: 1.2rem;"></i>
                            <span>Marketer Login Portal</span>
                        </h6>
                        <span class="card-title text-success">Live ↗</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
