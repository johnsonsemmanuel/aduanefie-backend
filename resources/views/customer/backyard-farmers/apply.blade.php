@extends('layouts.app')

@section('title', 'Apply to Be a Backyard Farmer')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-leaf"></i></span>
                <span>Apply to Be a Backyard Farmer</span>
            </h1>
            <p class="text-muted mb-0">
                Grow produce in your backyard and sell it on Aduanefie. We verify your farm,
                list it in nearby farming discovery, and your products carry a "Backyard Grown" badge.
            </p>
        </div>
        <a href="{{ route('backyard-farmer.index') }}" class="btn btn-outline-secondary btn-sm">Browse Backyard Farms</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Backyard Farm Application</h5></div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('backyard-farmer.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label class="font-weight-600">Farm Name</label>
                            <input type="text" name="farm_name" class="form-control" value="{{ old('farm_name') }}" placeholder="e.g. Nii's Backyard Garden" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Farm Size (acres)</label>
                                    <input type="number" name="farm_size_acres" class="form-control" step="0.01" value="{{ old('farm_size_acres') }}" placeholder="0.25" required>
                                    <div class="form-text">Typical backyard: 0.05 to 0.5 acres</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Primary Crops</label>
                                    <input type="text" name="primary_crops" class="form-control" value="{{ old('primary_crops') }}" placeholder="e.g. tomatoes, peppers, okra, maize" required>
                                    <div class="form-text">Comma-separated list of what you grow</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-600">Farming Experience</label>
                            <textarea name="farming_experience" class="form-control" rows="3">{{ old('farming_experience') }}</textarea>
                            <div class="form-text">Tell us about your gardening/farming background</div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-600">Farm Address</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address') }}" placeholder="Street, town, region" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Latitude</label>
                                    <input type="number" name="latitude" class="form-control" step="any" value="{{ old('latitude') }}" placeholder="5.6037" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Longitude</label>
                                    <input type="number" name="longitude" class="form-control" step="any" value="{{ old('longitude') }}" placeholder="-0.1870" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-600">Zone</label>
                            <select name="zone_id" class="form-control" required>
                                <option value="">Select your zone</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-600">Phone</label>
                                    <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="024XXXXXXXX" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-600">Farm Photos (up to 5)</label>
                            <input type="file" name="farm_photos[]" class="form-control-file" multiple accept="image/*">
                            <div class="form-text">Upload photos of your backyard farm (soil, beds, crops, etc.)</div>
                        </div>

                        <div class="form-group custom-checkbox">
                            <input type="checkbox" name="terms_accepted" id="terms_accepted" class="custom-control-input" required>
                            <label class="custom-control-label" for="terms_accepted">
                                I agree to the <a href="{{ route('terms-and-conditions') }}" target="_blank">Terms and Conditions</a> and confirm that the information provided is accurate.
                            </label>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary btn-lg btn-block">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">How It Works</h5></div>
                <div class="card-body">
                    <ol class="list-unstyled mb-0">
                        <li class="mb-3">
                            <strong>1. Apply</strong> – Submit your backyard farm details with photos and location.
                        </li>
                        <li class="mb-3">
                            <strong>2. Verify</strong> – A local marketer visits your farm to confirm it's a real backyard growing space.
                        </li>
                        <li class="mb-3">
                            <strong>3. Sell</strong> – Once approved, your farm becomes a vendor store. Add items and they appear in nearby farming with a "Backyard Grown" badge.
                        </li>
                        <li class="mb-3">
                            <strong>4. Deliver</strong> – Customers buy your harvest-ready produce and you deliver directly to them.
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
