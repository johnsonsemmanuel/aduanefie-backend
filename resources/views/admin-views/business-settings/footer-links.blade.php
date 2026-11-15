@extends('layouts.admin.app')

@section('title', 'Footer Links Management')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-link"></i></span>
                <span>Frontend Footer Columns &amp; Links</span>
            </h1>
            <p class="text-muted mb-0">
                Control the links displayed in the Aduanefie marketplace footer without code changes.
            </p>
        </div>
        <a href="https://aduanefie.com" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
            <i class="tio-open-in-new"></i> View Live Site
        </a>
    </div>

    <form action="{{ route('admin.footer-links.update') }}" method="POST">
        @csrf
        <div class="row g-3">
            @foreach (['markets' => 'Column 1: Agro Markets', 'partners' => 'Column 2: Trade & Partners'] as $group => $title)
                @php $rows = $footerLinks[$group] ?? []; @endphp
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">{{ $title }}</h5>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="addFooterRow('{{ $group }}')">+ Add Link</button>
                        </div>
                        <div class="card-body">
                            <div id="rows-{{ $group }}">
                                @foreach ($rows as $idx => $item)
                                    <div class="border rounded p-2 mb-2 footer-row">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-5">
                                                <input type="text" name="{{ $group }}_label[{{ $idx }}]" value="{{ $item['label'] ?? '' }}" class="form-control form-control-sm" placeholder="Label" required>
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" name="{{ $group }}_href[{{ $idx }}]" value="{{ $item['href'] ?? '' }}" class="form-control form-control-sm" placeholder="/path" required>
                                                <input type="hidden" name="{{ $group }}_key[{{ $idx }}]" value="{{ $item['key'] ?? '' }}">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-center justify-content-between">
                                                <input type="checkbox" name="{{ $group }}_active[{{ $idx }}]" value="1" {{ !empty($item['active']) ? 'checked' : '' }} title="Active">
                                                <button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('.footer-row').remove()">&times;</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save Footer Links</button>
        </div>
    </form>
</div>

<script>
function addFooterRow(group) {
    var container = document.getElementById('rows-' + group);
    var idx = container.querySelectorAll('.footer-row').length + Date.now();
    var div = document.createElement('div');
    div.className = 'border rounded p-2 mb-2 footer-row';
    div.innerHTML =
        '<div class="row g-2 align-items-center">' +
            '<div class="col-md-5"><input type="text" name="' + group + '_label[' + idx + ']" class="form-control form-control-sm" placeholder="Label" required></div>' +
            '<div class="col-md-5"><input type="text" name="' + group + '_href[' + idx + ']" class="form-control form-control-sm" placeholder="/path" required><input type="hidden" name="' + group + '_key[' + idx + ']" value=""></div>' +
            '<div class="col-md-2 d-flex align-items-center justify-content-between">' +
                '<input type="checkbox" name="' + group + '_active[' + idx + ']" value="1" checked title="Active">' +
                '<button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest(\'.footer-row\').remove()">&times;</button>' +
            '</div>' +
        '</div>';
    container.appendChild(div);
}
</script>
@endsection
