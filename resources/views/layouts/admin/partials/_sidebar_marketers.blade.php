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

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/marketer') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.marketer.index') }}" title="Marketers">
            <i class="tio-user nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Marketers</span>
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

    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/marketer/stores*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.marketer.stores') }}" title="Store attribution">
            <i class="tio-shop nav-icon"></i>
            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">Store attribution</span>
        </a>
    </li>
@endif
