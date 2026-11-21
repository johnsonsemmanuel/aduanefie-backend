{{--
    Remote-marketer admin screens, shown only to the super admin.

    The payout queue is the only place a marketer payout can be approved or
    recorded as paid, so this is deliberately outside the employee permission
    set: role 1 only, not module_permission_check().

    Included by every module sidebar because the module changes which sidebar
    the layout renders, and the pages themselves are module-independent.
--}}
@if (auth('admin')->user()?->role_id == 1)
    <li class="nav-item">
        <small class="nav-subtitle" title="Remote marketers">Remote marketers</small>
        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
    </li>

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/marketer') || Request::is('admin/marketer/stores*') || Request::is('admin/marketer-applications*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.marketer.index') }}" title="Remote Marketers">
            <i class="tio-user nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Remote Marketers</span>
        </a>
    </li>

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/marketer/commissions*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.marketer.commissions') }}" title="Commission ledger">
            <i class="tio-chart-pie-1 nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Commission ledger</span>
        </a>
    </li>

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/marketer/payouts*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.marketer.payouts') }}" title="Marketer payouts">
            <i class="tio-money nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Marketer payouts</span>
        </a>
    </li>

    <li class="nav-item">
        <small class="nav-subtitle" title="Agro & Field Programs">Agro &amp; Field Programs</small>
        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
    </li>

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/regional-tours*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.regional-tours.index') }}" title="Regional Tours">
            <i class="tio-map nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Regional Tours</span>
        </a>
    </li>

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/backyard-farmers*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.backyard-farmers.index') }}" title="Backyard Farmers">
            <i class="tio-home-vs-1-outlined nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Backyard Farmers</span>
        </a>
    </li>

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/farming-settings*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ url('/admin/business-settings/farming-settings') }}" title="Farming Settings">
            <i class="tio-tune nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Farming Settings</span>
        </a>
    </li>
@endif
