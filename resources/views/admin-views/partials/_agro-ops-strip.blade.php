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
                <img src="{{ asset('/public/assets/admin/img/grocery.svg') }}" class="w--26 mr-2" alt="">
                <span>Aduanefie Agribusiness &amp; Field Operations</span>
            </h5>
            <div class="btn--container">
                <a href="{{ route('admin.marketer.index') }}" class="btn btn--secondary">Remote Marketers</a>
                <a href="{{ route('admin.agro-prices.index') }}" class="btn btn--primary">Market Prices ({{ $opsAgroPrices }})</a>
            </div>
        </div>
    </div>
    <div class="card-body pt-1">
        <div class="row g-2 mb-3">
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('admin.marketer.index') }}" class="text-decoration-none d-block h-100">
                    <div class="__dashboard-card-2 h-100">
                        <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/customers.svg') }}" alt="marketers">
                        <h6 class="name">Remote Field Marketers</h6>
                        <h3 class="count">{{ $opsActiveMarketers }}</h3>
                        <div class="subtxt">{{ $opsPendingMarketers }} pending review</div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('admin.regional-tours.index') }}" class="text-decoration-none d-block h-100">
                    <div class="__dashboard-card-2 h-100">
                        <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/stores.svg') }}" alt="tours">
                        <h6 class="name">Active Regional Tours</h6>
                        <h3 class="count">{{ $opsUpcomingTours }}</h3>
                        <div class="subtxt">Field engagement stops</div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('admin.backyard-farmers.index') }}" class="text-decoration-none d-block h-100">
                    <div class="__dashboard-card-2 h-100">
                        <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/items.svg') }}" alt="crops">
                        <h6 class="name">Live Crops in Cultivation</h6>
                        <h3 class="count">{{ $opsLiveCrops }}</h3>
                        <div class="subtxt">{{ $opsPendingBackyard }} backyard apps pending</div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a href="{{ route('admin.business-settings.farming-settings') }}" class="text-decoration-none d-block h-100">
                    <div class="__dashboard-card-2 h-100">
                        <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/orders.svg') }}" alt="sms">
                        <h6 class="name">IntekHost SMS Credits</h6>
                        <h3 class="count">{{ isset($opsSmsBalance['units']) ? number_format($opsSmsBalance['units']) : '—' }}</h3>
                        <div class="subtxt">Sender ID: Aduanefie</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="{{ route('admin.marketer-applications.index') }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-user-add text-primary mr-2"></i>
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
                            <i class="tio-leaf text-success mr-2"></i>
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
                            <i class="tio-group-senior text-primary mr-2"></i>
                            <span>Open Group Buys</span>
                        </h6>
                        <span class="card-title text-primary">
                            {{ $opsOpenGroupBuys }}
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="{{ route('marketer.login') }}" target="_blank" rel="noopener">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle d-flex m-0 align-items-center">
                            <i class="tio-open-in-new text-primary mr-2"></i>
                            <span>Marketer Login Portal</span>
                        </h6>
                        <span class="card-title text-success">Live ↗</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
