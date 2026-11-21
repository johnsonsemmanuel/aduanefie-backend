@php
    $opsPendingMarketers = \Illuminate\Support\Facades\Schema::hasTable('marketer_applications')
        ? \App\Models\MarketerApplication::where('status', 'pending')->count() : 0;
    $opsPendingBackyard = \Illuminate\Support\Facades\Schema::hasTable('backyard_farm_applications')
        ? \App\Models\BackyardFarmApplication::where('status', 'pending')->count() : 0;
    $opsUpcomingTours = \Illuminate\Support\Facades\Schema::hasTable('regional_tours')
        ? \App\Models\RegionalTour::whereIn('status', ['upcoming', 'open', 'in_progress'])->count() : 0;
    $opsSmsBalance = \App\Traits\SmsGateway::intekhostBalance();
@endphp
<div class="row g-2 mb-3">
    <div class="col-sm-6 col-lg-3">
        <a class="order--card h-100" href="{{ route('admin.marketer-applications.index') }}">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-subtitle d-flex m-0 align-items-center">
                    <i class="tio-user-add text-primary mr-2" style="font-size: 1.25rem;"></i>
                    <span>{{ translate('Pending Marketers') }}</span>
                </h6>
                <span class="card-title {{ $opsPendingMarketers > 0 ? 'text-warning' : 'text-success' }}">
                    {{ $opsPendingMarketers }}
                </span>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a class="order--card h-100" href="{{ route('admin.regional-tours.index') }}">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-subtitle d-flex m-0 align-items-center">
                    <i class="tio-map-outlined text-info mr-2" style="font-size: 1.25rem;"></i>
                    <span>{{ translate('Active Regional Tours') }}</span>
                </h6>
                <span class="card-title text-3F8CE8">
                    {{ $opsUpcomingTours }}
                </span>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a class="order--card h-100" href="{{ route('admin.backyard-farmers.index') }}">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-subtitle d-flex m-0 align-items-center">
                    <i class="tio-leaf text-success mr-2" style="font-size: 1.25rem;"></i>
                    <span>{{ translate('Pending Backyard Farms') }}</span>
                </h6>
                <span class="card-title {{ $opsPendingBackyard > 0 ? 'text-warning' : 'text-success' }}">
                    {{ $opsPendingBackyard }}
                </span>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-lg-3">
        <a class="order--card h-100" href="{{ route('admin.business-settings.farming-settings') }}">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-subtitle d-flex m-0 align-items-center">
                    <i class="tio-sms text-primary mr-2" style="font-size: 1.25rem;"></i>
                    <span>{{ translate('IntekHost SMS Credits') }}</span>
                </h6>
                <span class="card-title {{ isset($opsSmsBalance['units']) && $opsSmsBalance['units'] < 100 ? 'text-danger' : 'text-success' }}">
                    {{ isset($opsSmsBalance['units']) ? number_format($opsSmsBalance['units']) : 'N/A' }}
                </span>
            </div>
        </a>
    </div>
</div>
