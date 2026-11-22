@extends('layouts.admin.app')
@section('title', 'Remote marketers')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1 class="page-header-title mb-3 mr-1">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>Remote marketers</span>
            </h1>
            <div class="btn--container mb-3">
                <a href="{{ route('admin.marketer-applications.index') }}" class="btn btn--secondary">Applications</a>
                <a href="{{ route('admin.marketer.stores') }}" class="btn btn--secondary">Store attribution</a>
                <a href="{{ route('admin.marketer.commissions') }}" class="btn btn--secondary">Commission ledger</a>
                <a href="{{ route('admin.marketer.payouts') }}" class="btn btn--secondary">
                    Payouts
                    @if($openPayouts > 0)
                        <span class="badge badge-soft-danger ml-1">{{ $openPayouts }}</span>
                    @endif
                </a>
                <a href="{{ route('marketer.login') }}" target="_blank" rel="noopener" class="btn btn--secondary">Marketer Portal ↗</a>
                <form method="post" action="{{ route('admin.marketer.accrue') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn--primary">Run accrual now</button>
                </form>
                <form method="post" action="{{ route('admin.marketer.notify-all') }}" class="mb-0">
                    @csrf
                    <input type="hidden" name="region" value="{{ $selectedRegion ?? '' }}">
                    <button type="submit" class="btn btn--secondary">Notify All (Email &amp; SMS)</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3 text-dark">
            Each remote marketer earns a <strong>fixed Onboarding Bounty</strong> (default <strong>{{ number_format((float) config('marketer.default_onboarding_bounty', 25), 2) }} {{ $currency }}</strong> per verified farmer/stakeholder onboarded)
            plus a <strong>Sales Commission Share</strong> (default <strong>{{ rtrim(rtrim(number_format((float) config('marketer.default_rate', 20), 2, '.', ''), '0'), '.') }}%</strong> of platform commission) on every delivered order from stores attributed to them.
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-6 mb-3 mb-lg-0">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">Add Remote Marketer</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.marketer.store') }}">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label class="input-label">First Name *</label>
                                <input type="text" name="f_name" class="form-control" required placeholder="e.g. Kwame">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label class="input-label">Last Name</label>
                                <input type="text" name="l_name" class="form-control" placeholder="e.g. Mensah">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Email *</label>
                                <input type="email" name="email" class="form-control" required placeholder="marketer@aduanefie.com">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Phone (SMS) *</label>
                                <input type="text" name="phone" class="form-control" required placeholder="e.g. 024XXXXXXX">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Region</label>
                                <input type="text" name="marketer_region" class="form-control" placeholder="e.g. Ashanti Region">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Referral Code</label>
                                <input type="text" name="marketer_code" class="form-control" placeholder="Auto (MKT-...)">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Bounty / Farmer ({{ $currency }})</label>
                                <input type="number" step="0.01" min="0" name="onboarding_bounty_ghs" class="form-control" placeholder="{{ config('marketer.default_onboarding_bounty', 25) }}">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label class="input-label">Sales Comm. (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="marketer_commission_rate" class="form-control" placeholder="{{ config('marketer.default_rate', 20) }}">
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="checkbox" name="send_notification" value="1" checked class="mr-2">
                                <span class="small">Send Welcome Email &amp; SMS with role &amp; login details</span>
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
                    <h5 class="card-title mb-0">Bulk Load Remote Marketers (CSV / Paste)</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.marketer.bulk-import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label class="input-label">Paste CSV Rows (<code>First Name, Last Name, Email, Phone, Code, Rate</code>)</label>
                            <textarea name="bulk_rows" rows="4" class="form-control" placeholder="Kwame, Mensah, kwame@aduanefie.com, 0244000001, MKT-BONO-01, 20&#10;Ama, Osei, ama@aduanefie.com, 0244000002, MKT-ASH-02, 20"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="input-label">Or Upload CSV File</label>
                            <input type="file" name="csv_file" accept=".csv,.txt" class="form-control">
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <label class="mb-0 d-flex align-items-center">
                                <input type="checkbox" name="send_notification" value="1" checked class="mr-2">
                                <span class="small">Send Welcome Email &amp; SMS to all imported marketers</span>
                            </label>
                            <button type="submit" class="btn btn--primary">Import &amp; Notify</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header py-2 border-0 d-flex flex-wrap align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        Marketers <span class="badge badge-soft-dark ml-2">{{ $marketers->count() }}</span>
                    </h5>
                    <form method="get" action="{{ route('admin.marketer.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                        <select name="region" class="form-control form-control-sm mr-2" style="width:auto;" onchange="this.form.submit()">
                            <option value="">All Regions</option>
                            @foreach(($regions ?? []) as $reg)
                                <option value="{{ $reg }}" {{ ($selectedRegion ?? '') === $reg ? 'selected' : '' }}>{{ $reg }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm mr-2" style="width:200px;" placeholder="Search name, email, phone, code...">
                        <button type="submit" class="btn btn-sm btn--primary">Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>Marketer &amp; Region</th>
                            <th>Referral Code &amp; Link</th>
                            <th>Farmers Onboarded</th>
                            <th>Onboarding Bounty</th>
                            <th>Total Farmer Sales</th>
                            <th>Comm. On Hold / Available</th>
                            <th>Paid</th>
                            <th>Bounty ({{ $currency }}) &amp; Comm. (%)</th>
                            <th>Notify</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($marketers as $row)
                            @php($m = $row['admin'])
                            @php($s = $row['summary'])
                            @php($refCode = $m->marketer_code ?: ('MKT-' . str_pad((string) $m->id, 4, '0', STR_PAD_LEFT)))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
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
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-soft-primary">{{ $refCode }}</span>
                                    <div class="small text-muted mt-1">
                                        <a href="https://aduanefie.com/store-registration?ref={{ urlencode($refCode) }}" target="_blank">Onboarding Link ↗</a>
                                    </div>
                                </td>
                                <td>
                                    <strong>{{ $s['stores'] }}</strong> total
                                    <div class="text-muted small">{{ $s['verified_stores'] ?? 0 }} verified · {{ (int) $s['stores'] - (int) ($s['verified_stores'] ?? 0) }} awaiting</div>
                                </td>
                                <td>
                                    <span class="font-weight-600">{{ number_format($s['onboarding_bounty_earned'] ?? 0, 2) }} {{ $s['currency'] }}</span>
                                    <div class="text-muted small">@ {{ number_format($s['onboarding_bounty_rate'] ?? 25, 2) }} / farmer</div>
                                </td>
                                <td>
                                    <span class="font-weight-600">{{ number_format($s['total_sales'] ?? 0, 2) }} {{ $s['currency'] }}</span>
                                    <div class="text-muted small">{{ $s['delivered_orders_count'] ?? 0 }} delivered orders</div>
                                </td>
                                <td>
                                    <div>Hold: {{ number_format($s['pending'], 2) }} {{ $s['currency'] }}</div>
                                    <div class="font-weight-600 text-success">Avail: {{ number_format($s['eligible'], 2) }} {{ $s['currency'] }}</div>
                                </td>
                                <td>
                                    {{ number_format($s['paid'], 2) }} {{ $s['currency'] }}
                                    <div class="text-muted small">{{ $s['paid_count'] }} orders</div>
                                </td>
                                <td style="min-width:210px;">
                                    <form method="post" action="{{ route('admin.marketer.rate', $m->id) }}" class="d-flex align-items-center">
                                        @csrf
                                        <input type="number" step="0.01" min="0" name="onboarding_bounty_ghs"
                                               value="{{ $m->onboarding_bounty_ghs !== null ? number_format((float) $m->onboarding_bounty_ghs, 2, '.', '') : '' }}"
                                               class="form-control form-control-sm mr-1" style="max-width:75px;"
                                               title="Per-farmer bounty ({{ $currency }})"
                                               placeholder="{{ number_format((float) config('marketer.default_onboarding_bounty', 25), 0) }}">
                                        <input type="number" step="0.001" min="0" max="100" name="marketer_commission_rate"
                                               value="{{ $m->marketer_commission_rate !== null ? rtrim(rtrim(number_format((float) $m->marketer_commission_rate, 3, '.', ''), '0'), '.') : '' }}"
                                               class="form-control form-control-sm mr-1" style="max-width:70px;"
                                               title="Sales commission (%)"
                                               placeholder="{{ rtrim(rtrim(number_format((float) config('marketer.default_rate', 20), 3, '.', ''), '0'), '.') }}">
                                        <span class="text-muted small mr-1">%</span>
                                        <button type="submit" class="btn btn-sm btn--primary">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" action="{{ route('admin.marketer.notify', $m->id) }}">
                                        @csrf
                                        <input type="hidden" name="reset_password" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Send Email &amp; SMS</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    No remote marketers added yet. Use the <strong>Add Remote Marketer</strong> or <strong>Bulk Load</strong> form above to onboard your field team.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
