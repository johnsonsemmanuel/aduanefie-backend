<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                @php($store_logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
                <a class="navbar-brand" href="{{ route('admin.dispatch.dashboard') }}" aria-label="Front">
                    <img class="navbar-brand-logo initial--36 onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                         src="{{ \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value ?? '', $store_logo?->storage[0]?->value ?? 'public', 'favicon') }}"
                         alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                         src="{{ \App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value ?? '', $store_logo?->storage[0]?->value ?? 'public', 'favicon') }}"
                         alt="Logo">
                </a>

                <button type="button" class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                    <i class="tio-clear tio-lg"></i>
                </button>

                <div class="navbar-nav-wrap-content-left">
                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip" data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align" data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'></i>
                    </button>
                </div>
            </div>

            <div class="navbar-vertical-content bg--005555" id="navbar-vertical-content">
                <form autocomplete="off" class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input autocomplete="false" name="qq" type="text" class="form-control form--control" placeholder="{{ translate('Search Menu...') }}" id="search">
                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>

                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.dashboard') }}?module_id={{ Config::get('module.current_module_id') }}" title="{{ translate('messages.dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.dashboard') }}</span>
                        </a>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('order'))
                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('Service Bookings') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/order*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Service Bookings') }}">
                            <i class="tio-event nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Service Bookings') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/order*') ? 'block' : 'none' }}">
                            <li class="nav-item {{ Request::is('admin/order/list/all') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['all']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.all') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">{{ \App\Models\Order::StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/scheduled') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['scheduled']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Scheduled Jobs') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">{{ \App\Models\Order::Scheduled()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/pending') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['pending']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('New Requests') }}
                                        <span class="badge badge-soft-info badge-pill ml-1">{{ \App\Models\Order::Pending()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/accepted') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['accepted']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Confirmed') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">{{ \App\Models\Order::AccepteByDeliveryman()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/processing') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['processing']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('In Progress') }}
                                        <span class="badge badge-soft-warning badge-pill ml-1">{{ \App\Models\Order::Preparing()->OrderScheduledIn(30)->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/delivered') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['delivered']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('Completed') }}
                                        <span class="badge badge-soft-success badge-pill ml-1">{{ \App\Models\Order::Delivered()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/list/canceled') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.list', ['canceled']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.canceled') }}
                                        <span class="badge badge-soft-warning bg-light badge-pill ml-1">{{ \App\Models\Order::Canceled()->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/order/offline/payment/list*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.order.offline_verification_list', ['all']) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate sidebar--badge-container">
                                        {{ translate('messages.Offline_Payments') }}
                                        <span class="badge badge-soft-danger bg-light badge-pill ml-1">{{ \App\Models\Order::has('offline_payments')->StoreOrder()->module(Config::get('module.current_module_id'))->count() }}</span>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('Service Promotions') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>
                    @if (\App\CentralLogics\Helpers::module_permission_check('banner'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/banner*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.banner.add-new') }}">
                            <i class="tio-image nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.banners') }}</span>
                        </a>
                    </li>
                    @endif
                    @if (\App\CentralLogics\Helpers::module_permission_check('coupon'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/coupon*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.coupon.add-new') }}">
                            <i class="tio-gift nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.coupons') }}</span>
                        </a>
                    </li>
                    @endif

                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('Service Catalog') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('category'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/category*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Service Categories') }}">
                            <i class="tio-category nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Service Categories') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/category*') ? 'block' : 'none' }}">
                            <li class="nav-item @yield('main_category') {{ request()->input('position') == 0 && Request::is('admin/category/add') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.category.add', ['position' => 0]) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.category') }}</span>
                                </a>
                            </li>
                            <li class="nav-item @yield('sub_category') {{ request()->input('position') == 1 && Request::is('admin/category/add') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.category.add', ['position' => 1]) }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('messages.sub_category') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('unit'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/unit*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.unit.index') }}" title="{{ translate('Billing Units') }}">
                            <i class="tio-ruler nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Rate Units (Hr/Acre)') }}</span>
                        </a>
                    </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('item'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/item*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Service Listings') }}">
                            <i class="tio-wrench nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Service Listings') }}</span>
                        </a>
                        <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/item*') ? 'block' : 'none' }}">
                            <li class="nav-item {{ Request::is('admin/item/add-new') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.item.add-new') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Add New Service') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/item/list') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.item.list') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('All Services') }}</span>
                                </a>
                            </li>
                            <li class="nav-item {{ Request::is('admin/item/reviews') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.item.reviews') }}">
                                    <span class="tio-circle nav-indicator-icon"></span>
                                    <span class="text-truncate">{{ translate('Service Reviews') }}</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('Farming Production') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('item'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/item/ongoing-farming') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.ongoing_farming') }}" title="{{ translate('messages.ongoing_farming') }}">
                            <span class="tio-leaf nav-icon"></span>
                            <span class="text-truncate sidebar--badge-container">
                                {{ translate('messages.ongoing_farming') }}
                                <span class="badge badge-soft-success badge-pill ml-1">{{ \App\Models\Item::ongoingFarming()->where('is_approved', 1)->count() }}</span>
                            </span>
                        </a>
                    </li>
                    @endif

                    <li class="nav-item">
                        <small class="nav-subtitle">{{ translate('Provider Management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>

                    @if (\App\CentralLogics\Helpers::module_permission_check('store'))
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/pending-requests') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.pending-requests') }}">
                            <span class="tio-calendar-note nav-icon"></span>
                            <span class="text-truncate position-relative overflow-visible">
                                {{ translate('New Providers') }}
                                @php($new_str = \App\Models\Store::whereHas('vendor', fn ($q) => $q->where('status', null))->module(Config::get('module.current_module_id'))->get())
                                @if (count($new_str) > 0)
                                <span class="btn-status btn-status-danger border-0 size-8px"></span>
                                @endif
                            </span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/add') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.add') }}">
                            <span class="tio-add-circle nav-icon"></span>
                            <span class="text-truncate">{{ translate('Add Service Provider') }}</span>
                        </a>
                    </li>
                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/store/list') || Request::is('admin/store/view/*') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.list') }}">
                            <span class="tio-layout nav-icon"></span>
                            <span class="text-truncate">{{ translate('Service Providers') }}</span>
                        </a>
                    </li>
                    <li class="navbar-item {{ Request::is('admin/store/recommended-store') ? 'active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.store.recommended_store') }}">
                            <span class="tio-hot nav-icon"></span>
                            <span class="text-truncate">{{ translate('Featured Providers') }}</span>
                        </a>
                    </li>
                    @endif

                    @include('layouts.admin.partials._sidebar_marketers')

                    <li class="nav-item py-5"></li>

                    <li class="__sidebar-hs-unfold px-2" id="tourb-9">
                        <div class="hs-unfold w-100">
                            <a class="js-hs-unfold-invoker navbar-dropdown-account-wrapper" href="javascript:;"
                                data-hs-unfold-options='{"target": "#accountNavbarDropdown", "type": "css-animation"}'>
                                <div class="cmn--media right-dropdown-icon d-flex align-items-center">
                                    <div class="avatar avatar-sm avatar-circle">
                                        <img class="avatar-img onerror-image"
                                            data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                            src="{{ auth('admin')->user()?->toArray()['image_full_url'] }}"
                                            alt="Image Description">
                                        <span class="avatar-status avatar-sm-status avatar-status-success"></span>
                                    </div>
                                    <div class="media-body pl-3">
                                        <span class="card-title h5">{{ auth('admin')->user()->f_name }} {{ auth('admin')->user()->l_name }}</span>
                                        <span class="card-text">{{ auth('admin')->user()->email }}</span>
                                    </div>
                                </div>
                            </a>
                            <div id="accountNavbarDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-right navbar-dropdown-menu navbar-dropdown-account min--240">
                                <a class="dropdown-item" href="{{ route('admin.settings') }}">
                                    <span class="text-truncate pr-2">{{ translate('messages.settings') }}</span>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item log-out" href="javascript:">
                                    <span class="text-truncate pr-2">{{ translate('messages.sign_out') }}</span>
                                </a>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</div>
<div id="sidebarCompact" class="d-none"></div>

@push('script_2')
<script>
    $(window).on('load', function() {
        if ($(".navbar-vertical-content li.active").length) {
            $('.navbar-vertical-content').animate({
                scrollTop: $(".navbar-vertical-content li.active").offset().top - 150
            }, 10);
        }
    });
    $(document).ready(function() {
        const $searchInput = $('#search');
        const $rows = $('#navbar-vertical-content li');
        $searchInput.on('keyup', function() {
            const val = $(this).val().toLowerCase();
            $rows.show().filter(function() {
                return !~$(this).text().replace(/\s+/g, ' ').toLowerCase().indexOf(val);
            }).hide();
        });
    });
</script>
@endpush
