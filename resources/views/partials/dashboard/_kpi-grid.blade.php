@php
    $items = $items ?? [];
    $colClass = $colClass ?? 'col-sm-6 col-lg-3';
@endphp
@if(!empty($items))
<div class="row g-2 mb-3">
    @foreach($items as $item)
        <div class="{{ $colClass }} mb-2">
            <div class="card h-100">
                <div class="card-body py-3 px-3">
                    <div class="text-muted small text-uppercase font-weight-bold mb-1" style="letter-spacing:0.03em;">
                        {{ $item['label'] ?? '' }}
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-dark">
                        {{ $item['value'] ?? '0' }}
                    </div>
                    @if(!empty($item['meta']))
                        <div class="text-muted small">
                            {{ $item['meta'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif
