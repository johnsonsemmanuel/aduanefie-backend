@php
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
    $icon = $icon ?? null;
    $badge = $badge ?? null;
@endphp
<div class="page-header mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-header-title mb-0 d-flex align-items-center">
                @if($icon)
                    <span class="page-header-icon mr-2">
                        <img src="{{ $icon }}" class="w--24" alt="">
                    </span>
                @endif
                <span>{{ $title }}</span>
                @if($badge !== null && $badge !== '')
                    <span class="badge badge-soft-dark ml-2">{{ $badge }}</span>
                @endif
            </h1>
            @if($subtitle)
                <div class="text-muted small mt-1">{{ $subtitle }}</div>
            @endif
        </div>
        @if(!empty($actionsHtml))
            <div class="btn--container align-items-center">
                {!! $actionsHtml !!}
            </div>
        @endif
    </div>
</div>
