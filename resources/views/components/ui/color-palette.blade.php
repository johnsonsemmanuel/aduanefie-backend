@props([
    'colors' => [
        ['name' => 'Primary', 'value' => '#67513A', 'var' => '--primary-clr'],
        ['name' => 'Secondary', 'value' => '#8B9F69', 'var' => '--secondary-clr'],
        ['name' => 'Accent', 'value' => '#5E7042', 'var' => '--accent-clr'],
    ],
    'active' => null,
    'onSelect' => null,
])

<div class="color-palette">
    @foreach($colors as $color)
        <a href="javascript:;"
           class="color-entry {{ $active === $color['name'] ? 'active' : '' }}"
           style="background-color: {{ $color['value'] }};"
           data-var="{{ $color['var'] }}"
           data-value="{{ $color['value'] }}"
           data-name="{{ $color['name'] }}"
           onclick="{{ $onSelect ?? 'applyColor(this)' }}">

            <div class="color-code-preview">
                <div class="color-preview" style="background-color: {{ $color['value'] }};"></div>
                <div class="color-code-hex">{{ $color['value'] }}</div>
                <div class="color-code-var">{{ $color['var'] }}</div>
                <div class="color-code-name">{{ $color['name'] }}</div>
            </div>

            @if($active === $color['name'])
                <i class="tio-check color-check"></i>
            @endif
        </a>
    @endforeach
</div>

<style>
    .color-palette {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 20px 0;
        padding: 16px;
        background: #FAF8F5;
        border: 1px solid #E0D6BD;
        border-radius: 8px;
    }

    .color-entry {
        position: relative;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .color-entry:hover {
        transform: scale(1.1);
        border-color: #67513A;
    }

    .color-entry.active {
        border-color: #222;
        transform: scale(1.15);
    }

    .color-check {
        color: #fff;
        font-size: 14px;
    }

    .color-code-preview {
        position: absolute;
        bottom: -60px;
        left: 50%;
        transform: translateX(-50%);
        background: #222;
        color: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 11px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }

    .color-entry:hover .color-code-preview,
    .color-entry.active .color-code-preview {
        opacity: 1;
    }

    .color-preview {
        width: 24px;
        height: 24px;
        border-radius: 4px;
        margin-right: 8px;
        display: inline-block;
        vertical-align: middle;
    }

    .color-code-hex {
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .color-code-var {
        opacity: 0.7;
        font-size: 10px;
    }

    .color-code-name {
        font-weight: 600;
        margin-top: 4px;
        display: block;
    }
</style>

<script>
    function applyColor(el) {
        var varName = el.getAttribute('data-var')
        var value = el.getAttribute('data-value')
        document.documentElement.style.setProperty(varName, value)

        // Mark active
        document.querySelectorAll('.color-entry').forEach(function (e) {
            e.classList.remove('active')
        })
        el.classList.add('active')
    }
</script>
