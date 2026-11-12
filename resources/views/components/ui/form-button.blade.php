@props([
    'label' => null,
    'type' => 'submit',
    'class' => 'btn--primary',
    'size' => 'lg',
    'block' => true,
    'loading' => false,
    'disabled' => false,
    'icon' => null,
])

<button type="{{ $type }}"
        class="btn btn-{{ $size }} btn-block btn--{{ $class }} {{ $block ? 'w-100' : '' }} {{ $disabled ? 'disabled' : '' }} {{ $loading ? 'loading' : '' }}"
        {{ $disabled ? 'disabled' : '' }}
        @if($loading) data-loading-text="{{ translate('messages.processing') }}" @endif>
    @if($icon) <i class="{{ $icon }} me-2"></i> @endif
    {{ $label }}
</button>

@if($loading)
    @push('scripts')
    <script>
        $(document).on('ready', function () {
            $('button[data-loading-text]').each(function () {
                var $btn = $(this)
                var originalText = $btn.html()
                $btn.on('click', function () {
                    $btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + $btn.data('loading-text'))
                    $btn.prop('disabled', true)
                    setTimeout(function () {
                        $btn.html(originalText)
                        $btn.prop('disabled', false)
                    }, 2000)
                })
            })
        })
    </script>
    @endpush
@endif
