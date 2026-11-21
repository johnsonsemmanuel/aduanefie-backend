@extends('layouts.admin.app')

@section('title', 'Remote Marketer Applications')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title mb-1">
                <span class="page-header-icon">
                    <i class="tio-user-add"></i>
                </span>
                <span>Remote Marketer Applications</span>
            </h1>
            <p class="text-muted mb-0">Review regional marketer applications. Approving automatically creates a Role-5 marketer account, assigns a referral code, and SMSs credentials.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group">
                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $lbl)
                    <a href="{{ route('admin.marketer-applications.index', ['status' => $k]) }}"
                       class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $lbl }}</a>
                @endforeach
            </div>
            <a href="{{ route('admin.marketer-applications.export', ['status' => $status]) }}" class="btn btn-sm btn-outline-primary">
                <i class="tio-download-to mr-1"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Applicant</th>
                        <th>Region &amp; District</th>
                        <th>MoMo Network</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                        <tr>
                            <td>
                                <strong>{{ $app->full_name }}</strong><br>
                                <small class="text-muted">{{ $app->email }} · {{ $app->phone }}</small>
                            </td>
                            <td>{{ $app->region }}@if($app->district)<br><small class="text-muted">{{ $app->district }}</small>@endif</td>
                            <td>{{ $app->momo_network ?? '-' }}</td>
                            <td style="max-width:260px;"><small>{{ $app->experience_notes ?? '-' }}</small></td>
                            <td><span class="badge badge-soft-{{ $app->status === 'approved' ? 'success' : ($app->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($app->status) }}</span></td>
                            <td>
                                @if($app->status === 'pending')
                                    <form method="post" action="{{ route('admin.marketer-applications.approve', $app->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Approve &amp; Create</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.marketer-applications.reject', $app->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                @else
                                    <small class="text-muted">{{ optional($app->reviewed_at)->format('M d, Y') }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No marketer applications for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h4 class="mb-0">Active Remote Marketers ({{ $totalMarketers ?? 0 }})</h4>
                <small class="text-muted">Send one-click signed login links via SMS &amp; Email so marketers can access <strong>partners.aduanefie.com/marketer-login</strong>.</small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if(($missingCodeCount ?? 0) > 0)
                    <form method="post" action="{{ route('admin.marketer-applications.backfill-codes') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">Backfill {{ $missingCodeCount }} Missing Code(s)</button>
                    </form>
                @endif
                <form method="get" action="{{ route('admin.marketer-applications.index') }}" class="d-flex gap-1">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="text" name="q" value="{{ $search ?? '' }}" class="form-control form-control-sm" placeholder="Search name, email, code...">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
                </form>
            </div>
        </div>
        <div class="card-body border-bottom py-2">
            <form method="post" action="{{ route('admin.marketer-applications.send-batch') }}" class="d-flex flex-wrap align-items-center gap-2" onsubmit="return confirm('Send SMS + Email sign-in links to this batch of marketers?');">
                @csrf
                <span class="small font-weight-bold">Batch Re-Engage (SMS + Email):</span>
                <label class="small mb-0">Offset</label>
                <input type="number" name="offset" value="0" min="0" class="form-control form-control-sm" style="width:85px;">
                <label class="small mb-0">Count</label>
                <input type="number" name="limit" value="20" min="1" max="30" class="form-control form-control-sm" style="width:75px;">
                <button type="submit" class="btn btn-sm btn-primary">Send Login Batch</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Marketer</th>
                        <th>Phone &amp; Email</th>
                        <th>Region &amp; Code</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeMarketers ?? [] as $m)
                        <tr>
                            <td>#{{ $m->id }}</td>
                            <td>
                                <a href="{{ route('admin.marketer-applications.show', $m->id) }}" class="font-weight-bold text-dark">
                                    {{ trim($m->f_name . ' ' . $m->l_name) }}
                                </a>
                            </td>
                            <td>
                                <small>{{ $m->phone ?: '-' }}</small><br>
                                <small class="text-muted">{{ $m->email }}</small>
                            </td>
                            <td>
                                {{ $m->marketer_region ?: 'Unassigned' }}<br>
                                @if($m->marketer_code)
                                    <span class="badge badge-soft-info">{{ $m->marketer_code }}</span>
                                @else
                                    <span class="badge badge-soft-warning">No Code</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.marketer-applications.show', $m->id) }}" class="btn btn-sm btn-outline-info mr-1">
                                    <i class="tio-visible-outlined"></i> View Profile
                                </a>
                                <form method="post" action="{{ route('admin.marketer-applications.send-login', $m->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Send Login Link (SMS + Email)</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No remote marketers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($activeMarketers))
            <div class="card-footer">{{ $activeMarketers->links() }}</div>
        @endif
    </div>
</div>
@endsection
