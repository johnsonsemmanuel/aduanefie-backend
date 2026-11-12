@extends('layouts.admin.app')

@section('title', translate('messages.ongoing_farming'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center g-2">
                <div class="col-md-9 col-12">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{asset('public/assets/admin/img/items.png')}}" class="w--22" alt="">
                        </span>
                        <span>
                            {{translate('messages.ongoing_farming')}}
                            <span class="badge badge-soft-success ml-2">{{$farmingCount}}</span>
                        </span>
                    </h1>
                </div>
                <div class="col-md-3 col-12 text-end">
                    <a href="{{route('admin.item.list')}}" class="btn btn--primary btn-outline-primary">
                        <i class="tio-list me-2"></i>{{translate('messages.all_items')}}
                    </a>
                </div>
            </div>
        </div>
        <!-- End Page Header -->

        <!-- Stats Summary -->
        <div class="row mb-4">
            <div class="col-md-6 col-12">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="text-white opacity-75">{{translate('messages.currently_farming')}}</h6>
                        <h3 class="mb-0">{{$farmingCount}}</h3>
                        <p class="small text-white opacity-75">{{translate('messages.products_in_farming_mode')}}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-12">
                <div class="card stat-card bg-secondary text-white">
                    <div class="card-body">
                        <h6 class="text-white opacity-75">{{translate('messages.backyard_farms')}}</h6>
                        <h3 class="mb-0">{{$backyardCount}}</h3>
                        <p class="small text-white opacity-75">{{translate('messages.backyard_grown_products')}}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Farming Status Filter -->
        <div class="card mb-3">
            <div class="card-header py-2 border-0">
                <h6>{{ translate('messages.farming_products') }}</h6>
            </div>
            <div class="card-body">
                <form method="get" action="{{route('admin.ongoing_farming')}}" class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">{{ translate('messages.select_store') }}</label>
                        <select name="store_id" id="store" class="form-control js-select2-custom">
                            <option value="all" {{ $store_id == 'all' ? 'selected' : '' }}>{{translate('messages.all_stores')}}</option>
                            @if($store_id != 'all')
                            <option value="{{$store_id}}" selected>{{ \App\Models\Store::find($store_id)?->name ?? '' }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">{{ translate('messages.search') }}</label>
                        <input type="text" name="search" class="form-control" placeholder="{{translate('messages.search_products')}}" value="{{request('search')}}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn--primary">{{translate('messages.filter')}}</button>
                        <a href="{{route('admin.ongoing_farming')}}" class="btn btn--outline-secondary">{{translate('messages.reset')}}</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card mb-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('sl')}}</th>
                            <th class="border-0">{{translate('messages.name')}}</th>
                            <th class="border-0">{{translate('messages.farm_type')}}</th>
                            <th class="border-0 text-center">{{translate('messages.farming_status')}}</th>
                            <th class="border-0 text-center">{{translate('messages.store')}}</th>
                            <th class="border-0 text-center">{{translate('messages.price')}}</th>
                            <th class="border-0 text-center">{{translate('messages.action')}}</th>
                        </tr>
                        </thead>
                        <tbody id="set-rows">
                        @foreach($items as $key=>$item)
                            <tr>
                                <td>{{$key+$items->firstItem()}}</td>
                                <td>
                                    <a class="media align-items-center" href="{{route('admin.item.view',[$item['id']])}}">
                                        <img class="avatar avatar-lg mr-3 onerror-image"
                                             src="{{ $item['image_full_url'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
                                        <div class="media-body">
                                            <h5 class="text-hover-primary mb-0">{{Str::limit($item['name'],25,'...')}}</h5>
                                            @if($item->is_backyard)
                                                <span class="badge badge-soft-success mt-1">{{translate('messages.backyard_grown')}}</span>
                                            @endif
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-soft-info">{{ucfirst($item->store?->farm_type ?? 'commercial')}}</span>
                                </td>
                                <td class="text-center">
                                    @if($item->farming_status === 'farming')
                                        <span class="badge badge-soft-warning">{{translate('messages.farming')}}</span>
                                    @elseif($item->farming_status === 'harvest_ready')
                                        <span class="badge badge-soft-success">{{translate('messages.harvest_ready')}}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{translate('messages.in_stock')}}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->store)
                                    <a title="{{ $item?->store?->name }}" href="{{route('admin.store.view', $item->store->id)}}" class="table-rest-info" alt="view store">{{  Str::limit($item->store->name, 20, '...') }}</a>
                                    @else
                                    {{  translate('messages.store_deleted') }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="text-right mw--85px">
                                        {{\App\CentralLogics\Helpers::format_currency($item['price'])}}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary btn-sm"
                                            href="{{route('admin.item.edit',[$item['id']])}}" title="{{translate('messages.edit_item')}}"><i class="tio-edit"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($items) !== 0)
                <hr>
            @endif
            <div class="page-area">
                <tfoot class="border-top">
                {!! $items->withQueryString()->links() !!}
                </div>
            </div>
            @if(count($items) === 0)
                <div class="empty--data text-center py-5">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>{{translate('no_data_found')}}</h5>
                    <p class="text-muted">{{translate('messages.no_ongoing_farming_products')}}</p>
                </div>
            @endif
        </div>
        <!-- End Card -->
    </div>
@endsection

@push('css_or_js')
<style>
    .stat-card {
        border-radius: 12px;
        border: none;
    }
    .badge-soft-warning {
        background-color: #fff3cd;
        color: #856404;
    }
    .badge-soft-success {
        background-color: #d4edda;
        color: #155724;
    }
    .badge-soft-info {
        background-color: #cce5ff;
        color: #004085;
    }
    .badge-soft-secondary {
        background-color: #e2e3e5;
        color: #383d41;
    }
</style>
@endpush
