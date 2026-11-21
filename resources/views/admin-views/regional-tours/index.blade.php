@extends('layouts.admin.app')

@section('title', 'Regional Tours & Farmer Engagement')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/banner.png') }}" class="w--26" alt="">
                    </span>
                    <span>Regional Tours &amp; Farmer Visits</span>
                </h1>
            </div>
            <div class="col-sm-auto">
                <div class="btn--container">
                    <a href="{{ route('admin.regional-tours.export', ['region' => $region]) }}" class="btn btn--primary">
                        <i class="tio-download-to mr-1"></i> Export Registrations CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Schedule New Regional Tour</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.regional-tours.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Tour Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Ashanti Cocoa & Produce Onboarding Tour">
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Region</label>
                                <select name="region" class="form-control" required>
                                    @foreach($regions as $r)
                                        <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>District / Town</label>
                                <input type="text" name="district" class="form-control" placeholder="e.g. Ejisu / Kumasi">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Venue</label>
                            <input type="text" name="venue" class="form-control" placeholder="Community Centre / Agric Office">
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Capacity</label>
                                <input type="number" name="capacity" class="form-control" value="100" min="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Lead Regional Marketer</label>
                            <select name="lead_marketer_id" class="form-control">
                                <option value="">-- Unassigned --</option>
                                @foreach($marketers as $m)
                                    <option value="{{ $m->id }}">{{ $m->f_name }} {{ $m->l_name }} ({{ $m->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Create Regional Tour</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Farmer Visit Demand by Region</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm table-borderless table-thead-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Region</th>
                                <th>Signups / Requests</th>
                                <th>Total Farmers Represented</th>
                                <th>Onboarded</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($demandByRegion as $row)
                                <tr>
                                    <td><strong>{{ $row->region }}</strong></td>
                                    <td>{{ $row->requests_count }}</td>
                                    <td>{{ $row->total_farmers }}</td>
                                    <td><span class="badge badge-soft-success">{{ $row->onboarded_count }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No regional tour registrations yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Scheduled Tours</h5></div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Title</th>
                        <th>Region &amp; Venue</th>
                        <th>Date</th>
                        <th>Lead Marketer</th>
                        <th>Registered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tours as $tour)
                        <tr>
                            <td><strong>{{ $tour->title }}</strong></td>
                            <td>{{ $tour->region }}<br><small class="text-muted">{{ $tour->venue ?? $tour->district ?? '-' }}</small></td>
                            <td>{{ optional($tour->start_date)->format('M d, Y') }}</td>
                            <td>{{ $tour->leadMarketer ? ($tour->leadMarketer->f_name . ' ' . $tour->leadMarketer->l_name) : '-' }}</td>
                            <td>{{ $tour->registrations_count }} / {{ $tour->capacity }}</td>
                            <td><span class="badge badge-soft-info">{{ ucfirst(str_replace('_', ' ', $tour->status)) }}</span></td>
                            <td>
                                <form method="post" action="{{ route('admin.regional-tours.broadcast', $tour->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success">Send SMS Reminder</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No tours scheduled yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Recent Farmer Registrations &amp; Visit Requests</h5></div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Farmer</th>
                        <th>Phone</th>
                        <th>Region / Community</th>
                        <th>Crops</th>
                        <th>Tour</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($registrations as $reg)
                        <tr>
                            <td><strong>{{ $reg->farmer_name }}</strong> @if($reg->is_cooperative)<span class="badge badge-soft-primary">Co-op ({{ $reg->farmer_count }})</span>@endif</td>
                            <td>{{ $reg->phone }}</td>
                            <td>{{ $reg->region }} {{ $reg->community ? '· ' . $reg->community : '' }}</td>
                            <td>{{ $reg->primary_crops ?? '-' }}</td>
                            <td>{{ $reg->tour?->title ?? 'Open Visit Request' }}</td>
                            <td><span class="badge badge-soft-secondary">{{ ucfirst(str_replace('_', ' ', $reg->status)) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No registrations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
