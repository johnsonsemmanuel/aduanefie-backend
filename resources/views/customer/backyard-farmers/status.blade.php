@extends('layouts.app')

@section('title', 'Application Status - #' . $application->id)

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-file-text"></i></span>
                <span>Application Status</span>
            </h1>
            <p class="text-muted mb-0">Backyard farm application #{{ $application->id }}</p>
        </div>
        <a href="{{ route('backyard-farmer.apply') }}" class="btn btn-outline-secondary btn-sm">Apply Again</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Application Details</h5></div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr><th style="width:30%">Farm Name</th><td>{{ $application->farm_name }}</td></tr>
                        <tr><th>Farm Type</th><td><span class="badge badge-soft-success">{{ ucfirst($application->farm_type) }}</span></td></tr>
                        <tr><th>Size</th><td>{{ number_format($application->farm_size_acres, 2) }} acres</td></tr>
                        <tr><th>Primary Crops</th><td>{{ $application->primary_crops }}</td></tr>
                        <tr><th>Address</th><td>{{ $application->address }}</td></tr>
                        <tr><th>Email</th><td>{{ $application->guest_email }}</td></tr>
                        <tr><th>Phone</th><td>{{ $application->guest_phone }}</td></tr>
                        <tr><th>Submitted</th><td>{{ $application->created_at?->format('M d, Y H:i') }}</td></tr>
                    </table>

                    @if ($application->farm_photos && count($application->farm_photos) > 0)
                        <h6>Farm Photos</h6>
                        <div class="row">
                            @foreach($application->farm_photos as $photo)
                                <div class="col-md-3 mb-2">
                                    <img src="{{ Helpers::get_full_url('backyard-farms', $photo, 'public') ?? $photo }}" class="img-fluid img-thumbnail" alt="Farm photo">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Status</h5></div>
                <div class="card-body text-center">
                    @switch($application->status)
                        @case('pending')
                            <span class="badge badge-soft-warning" style="font-size: 1.5rem;">Pending</span>
                            <p class="mt-2">Your application is being reviewed. A marketer will contact you to schedule a farm visit.</p>
                        @break
                        @case('verified')
                            <span class="badge badge-soft-info" style="font-size: 1.5rem;">Verified</span>
                            <p class="mt-2">Your farm has been verified. Approval is pending final admin sign-off.</p>
                        @break
                        @case('approved')
                            <span class="badge badge-soft-success" style="font-size: 1.5rem;">Approved 🎉</span>
                            <p class="mt-2">Congratulations! Your backyard farm is now live on Aduanefie. You can start adding products.</p>
                            <a href="{{ route('store.login') }}" class="btn btn-primary mt-3">Go to Vendor Dashboard</a>
                        @break
                        @case('rejected')
                            <span class="badge badge-soft-danger" style="font-size: 1.5rem;">Rejected</span>
                            <p class="mt-2">{{ $application->rejection_reason ?? 'Your application was not approved at this time.' }}</p>
                            <a href="{{ route('backyard-farmer.apply') }}" class="btn btn-outline-danger mt-3">Apply Again</a>
                        @break
                    @endswitch
                </div>
            </div>

            @if ($application->verification_notes)
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Marketer Notes</h5></div>
                    <div class="card-body">
                        <p>{{ $application->verification_notes }}</p>
                        @if ($application->verifier)
                            <small class="text-muted">Verified by {{ $application->verifier->f_name }} {{ $application->verifier->l_name }} on {{ $application->verified_at?->format('M d, Y') }}</small>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card sticky-top" style="top: 1rem;">
                <div class="card-body">
                    <h6 class="mb-3">Quick Actions</h6>
                    @if ($application->isPending())
                        <button class="btn btn-outline-info btn-block mb-2" disabled>Waiting for verification</button>
                    @elseif ($application->isVerified())
                        <button class="btn btn-outline-primary btn-block mb-2" disabled>Approved pending admin</button>
                    @elseif ($application->isApproved())
                        <a href="{{ route('store.login') }}" class="btn btn-primary btn-block mb-2">Start Selling</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
