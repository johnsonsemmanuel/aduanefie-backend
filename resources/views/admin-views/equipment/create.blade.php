@extends('layouts.admin.app')

@section('title', translate('Add New Equipment'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <i class="tio-shop"></i>
                        </span>
                        <span>{{ translate('Add New Equipment') }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn-sm btn-white" href="{{ route('admin.equipment.index') }}">
                        <i class="tio-back-from-editing"></i> {{ translate('Back') }}
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.equipment.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            @include('admin-views.equipment._form')
            <div class="col-12 mt-3">
                <div class="btn--container justify-content-end">
                    <button type="reset" class="btn btn--reset">{{ translate('Reset') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Submit') }}</button>
                </div>
            </div>
        </form>
    </div>
@endsection
