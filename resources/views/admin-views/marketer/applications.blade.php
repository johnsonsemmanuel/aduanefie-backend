@extends('layouts.admin.app')

@section('title', 'Remote Marketer Applications')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                    </span>
                    <span>Remote Marketer Applications</span>
                </h1>
            </div>
            <div class="col-sm-auto">
                <div class="btn--container">
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $lbl)
                        <a href="{{ route('admin.marketer-applications.index', ['status' => $k]) }}"
                           class="btn {{ $status === $k ? 'btn--primary' : 'btn--secondary' }}">{{ $lbl }}</a>
                    @endforeach
                    <a href="{{ route('admin.marketer-applications.export', ['status' => $status]) }}" class="btn btn-outline-primary">
                        <i class="tio-download-to mr-1"></i> Export CSV
                    </a>
                </div>
            </div>
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
        <div class="page-area px-3 pb-3">{{ $applications->links('pagination::bootstrap-4') }}</div>
    </div>

    <div class="card mt-3">
        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
                <h5 class="card-title">
                    Active Remote Marketers
                    <span class="badge badge-soft-dark ml-2">{{ $totalMarketers ?? 0 }}</span>
                </h5>
                <form method="get" action="{{ route('admin.marketer-applications.index') }}" class="search-form">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <div class="input-group input--group">
                        <input type="search" name="q" value="{{ $search ?? '' }}" class="form-control" placeholder="Search name, email, code...">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
                <div class="btn--container ml-2">
                    @if(($missingCodeCount ?? 0) > 0)
                        <form method="post" action="{{ route('admin.marketer-applications.backfill-codes') }}" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn--warning">Backfill {{ $missingCodeCount }} Codes</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body border-top border-bottom py-2">
            <form method="post" action="{{ route('admin.marketer-applications.send-batch') }}" class="row align-items-center" onsubmit="return confirm('Send SMS + Email sign-in links to this batch of marketers?');">
                @csrf
                <div class="col-auto"><span class="small font-weight-bold text-dark">Batch Re-Engage (SMS + Email):</span></div>
                <div class="col-auto d-flex align-items-center">
                    <label class="small mb-0 mr-2">Offset</label>
                    <input type="number" name="offset" value="0" min="0" class="form-control form-control-sm" style="width:85px;">
                </div>
                <div class="col-auto d-flex align-items-center">
                    <label class="small mb-0 mr-2">Count</label>
                    <input type="number" name="limit" value="20" min="1" max="30" class="form-control form-control-sm" style="width:75px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn--primary">Send Login Batch</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Marketer</th>
                        <th>Phone &amp; Email</th>
                        <th>Region &amp; Code</th>
                        <th class="text-center">Actions</th>
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
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a href="{{ route('admin.marketer-applications.show', $m->id) }}"
                                       class="btn action-btn btn--warning btn-outline-warning"
                                       title="View Marketer Profile">
                                        <i class="tio-visible-outlined"></i>
                                    </a>
                                    <form method="post" action="{{ route('admin.marketer-applications.send-login', $m->id) }}" class="mb-0">
                                        @csrf
                                        <button type="submit"
                                                class="btn action-btn btn--primary btn-outline-primary"
                                                title="Send Login Link (SMS + Email)">
                                            <i class="tio-send"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No remote marketers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($activeMarketers))
            <div class="page-area px-3 py-3">{{ $activeMarketers->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>
</div>
@endsection
