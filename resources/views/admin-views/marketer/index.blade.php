@extends('layouts.admin.app')
@section('title', 'Remote marketers')

@section('content')
<div class="content container-fluid">
    @php
        $defaultBounty = number_format((float) config('marketer.default_onboarding_bounty', 25), 2);
        $defaultRate = rtrim(rtrim(number_format((float) config('marketer.default_rate', 20), 2, '.', ''), '0'), '.');
        $payoutBadge = $openPayouts > 0 ? '<span class="badge badge-soft-danger ml-1">' . $openPayouts . '</span>' : '';
        $headerActions = '
            <a href="' . route('admin.marketer-applications.index') . '" class="btn btn--secondary">Applications</a>
            <a href="' . route('admin.marketer.stores') . '" class="btn btn--secondary">Store attribution</a>
            <a href="' . route('admin.marketer.commissions') . '" class="btn btn--secondary">Commissions</a>
            <a href="' . route('admin.marketer.payouts') . '" class="btn btn--secondary">Payouts' . $payoutBadge . '</a>
            <a href="' . route('marketer.login') . '" target="_blank" rel="noopener" class="btn btn--secondary">Marketer Portal</a>
            <form method="post" action="' . route('admin.marketer.send-reset-all') . '" class="mb-0" onsubmit="return confirm(\'Send account reset emails to all listed marketers?\');">'
                . csrf_field() .
                '<input type="hidden" name="region" value="' . e($selectedRegion ?? '') . '">
                <button type="submit" class="btn btn--secondary">Bulk Send Reset Emails</button>
            </form>
            <form method="post" action="' . route('admin.marketer.notify-all') . '" class="mb-0">'
                . csrf_field() .
                '<input type="hidden" name="region" value="' . e($selectedRegion ?? '') . '">
                <button type="submit" class="btn btn--secondary">Notify All</button>
            </form>
            <form method="post" action="' . route('admin.marketer.accrue') . '" class="mb-0">'
                . csrf_field() .
                '<button type="submit" class="btn btn--primary">Run accrual</button>
            </form>
        ';
    @endphp

    @include('partials.dashboard._page-header', [
        'title' => 'Remote marketers',
        'subtitle' => 'Default rates: ' . $defaultBounty . ' ' . $currency . ' bounty per verified farmer · ' . $defaultRate . '% commission share',
        'icon' => asset('public/assets/admin/img/role.png'),
        'badge' => $marketers->count(),
        'actionsHtml' => $headerActions,
    ])

    <div class="row mb-3">
        <div class="col-lg-6 mb-3 mb-lg-0">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">Add Marketer</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.marketer.store') }}">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label class="input-label">First Name *</label>
                                <input type="text" name="f_name" class="form-control" required placeholder="Kwame">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label class="input-label">Last Name</label>
                                <input type="text" name="l_name" class="form-control" placeholder="Mensah">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Email *</label>
                                <input type="email" name="email" class="form-control" required placeholder="marketer@aduanefie.com">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Phone (SMS) *</label>
                                <input type="text" name="phone" class="form-control" required placeholder="024XXXXXXX">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Region</label>
                                <input type="text" name="marketer_region" class="form-control" placeholder="Ashanti Region">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Referral Code</label>
                                <input type="text" name="marketer_code" class="form-control" placeholder="Auto (MKT-...)">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Bounty ({{ $currency }})</label>
                                <input type="number" step="0.01" min="0" name="onboarding_bounty_ghs" class="form-control" placeholder="{{ config('marketer.default_onboarding_bounty', 25) }}">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Commission (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="marketer_commission_rate" class="form-control" placeholder="{{ config('marketer.default_rate', 20) }}">
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="checkbox" name="send_notification" value="1" checked class="mr-2">
                                <span class="small">Send welcome email &amp; SMS</span>
                            </label>
                            <button type="submit" class="btn btn--primary">Save Marketer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">Bulk Import (CSV)</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.marketer.bulk-import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label class="input-label">CSV Rows (<code>First Name, Last Name, Email, Phone, Code, Rate</code>)</label>
                            <textarea name="bulk_rows" rows="4" class="form-control" placeholder="Kwame, Mensah, kwame@aduanefie.com, 0244000001, MKT-BONO-01, 20"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="input-label">CSV File</label>
                            <input type="file" name="csv_file" accept=".csv,.txt" class="form-control">
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="checkbox" name="send_notification" value="1" checked class="mr-2">
                                <span class="small">Send welcome email &amp; SMS</span>
                            </label>
                            <button type="submit" class="btn btn--primary">Import Marketers</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 border-0 d-flex flex-wrap align-items-center justify-content-between">
            <h5 class="card-title mb-0">Marketer Directory</h5>
            <form method="get" action="{{ route('admin.marketer.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                <select name="region" class="form-control form-control-sm mr-2" style="width:auto;" onchange="this.form.submit()">
                    <option value="">All Regions</option>
                    @foreach(($regions ?? []) as $reg)
                        <option value="{{ $reg }}" {{ ($selectedRegion ?? '') === $reg ? 'selected' : '' }}>{{ $reg }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm mr-2" style="width:200px;" placeholder="Name, email, phone, code">
                <button type="submit" class="btn btn-sm btn--primary">Filter</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>Marketer</th>
                    <th>Referral Code</th>
                    <th>Farmers</th>
                    <th>Earnings ({{ $currency }})</th>
                    <th>Available / Paid</th>
                    <th>Rates ({{ $currency }} / %)</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($marketers as $row)
                    @php($m = $row['admin'])
                    @php($s = $row['summary'])
                    @php($refCode = $m->marketer_code ?: ('MKT-' . str_pad((string) $m->id, 4, '0', STR_PAD_LEFT)))
                    <tr>
                        <td>
                            <div class="font-weight-600">
                                <a href="{{ route('admin.marketer-applications.show', $m->id) }}" class="text-dark">{{ $m->f_name }} {{ $m->l_name }}</a>
                            </div>
                            <div class="text-muted small">{{ $m->email }}</div>
                            @if(!empty($m->phone) && !str_starts_with((string) $m->phone, '000000'))
                                <div class="text-muted small">{{ $m->phone }}</div>
                            @endif
                            @if(!empty($m->marketer_region))
                                <span class="badge badge-soft-info mt-1">{{ $m->marketer_region }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-soft-primary">{{ $refCode }}</span>
                            <div class="small text-muted mt-1">
                                <a href="https://aduanefie.com/store-registration?ref={{ urlencode($refCode) }}" target="_blank" rel="noopener">Registration Link</a>
                            </div>
                        </td>
                        <td>
                            <strong>{{ $s['stores'] }}</strong>
                            <div class="text-muted small">{{ $s['verified_stores'] ?? 0 }} verified · {{ (int) $s['stores'] - (int) ($s['verified_stores'] ?? 0) }} pending</div>
                        </td>
                        <td>
                            <div class="font-weight-600">Bounty: {{ number_format($s['onboarding_bounty_earned'] ?? 0, 2) }}</div>
                            <div class="text-muted small">Sales: {{ number_format($s['total_sales'] ?? 0, 2) }} ({{ $s['delivered_orders_count'] ?? 0 }} orders)</div>
                        </td>
                        <td>
                            <div class="font-weight-600 text-success">Avail: {{ number_format($s['eligible'], 2) }}</div>
                            <div class="text-muted small">Hold: {{ number_format($s['pending'], 2) }} · Paid: {{ number_format($s['paid'], 2) }}</div>
                        </td>
                        <td style="min-width:205px;">
                            <form method="post" action="{{ route('admin.marketer.rate', $m->id) }}" class="d-flex align-items-center">
                                @csrf
                                <input type="number" step="0.01" min="0" name="onboarding_bounty_ghs"
                                       value="{{ $m->onboarding_bounty_ghs !== null ? number_format((float) $m->onboarding_bounty_ghs, 2, '.', '') : '' }}"
                                       class="form-control form-control-sm mr-1" style="max-width:72px;"
                                       title="Per-farmer bounty ({{ $currency }})"
                                       placeholder="{{ number_format((float) config('marketer.default_onboarding_bounty', 25), 0) }}">
                                <input type="number" step="0.001" min="0" max="100" name="marketer_commission_rate"
                                       value="{{ $m->marketer_commission_rate !== null ? rtrim(rtrim(number_format((float) $m->marketer_commission_rate, 3, '.', ''), '0'), '.') : '' }}"
                                       class="form-control form-control-sm mr-1" style="max-width:68px;"
                                       title="Sales commission (%)"
                                       placeholder="{{ rtrim(rtrim(number_format((float) config('marketer.default_rate', 20), 3, '.', ''), '0'), '.') }}">
                                <button type="submit" class="btn btn-sm btn--primary">Save</button>
                            </form>
                        </td>
                        <td>
                            <div class="btn--container">
                                <form method="post" action="{{ route('admin.marketer.send-reset', $m->id) }}" class="mb-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn--secondary">Send Reset</button>
                                </form>
                                <form method="post" action="{{ route('admin.marketer.notify', $m->id) }}" class="mb-0">
                                    @csrf
                                    <input type="hidden" name="reset_password" value="1">
                                    <button type="submit" class="btn btn-sm btn--secondary">Email &amp; SMS</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No remote marketers added yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
