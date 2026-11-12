@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => true,
    'class' => null,
    'inputClass' => null,
    'icon' => null,
    'autocomplete' => null,
    'dataMsg' => null,
    'togglePassword' => false,
])

<div class="form-group{{ $errors->has($name) ? ' has-error' : '' }} mb-3">
    @if($label)
        <label class="input-label text-capitalize d-block mb-2" for="{{ $name }}">
            {{ $label }}
            @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif

    <div class="input-group input-group-merge{{ $togglePassword ? ' password-toggle' : '' }} position-relative">
        @if($icon)
            <span class="input-group-text bg-transparent border-0 position-absolute left-0">
                <i class="{{ $icon }}"></i>
            </span>
        @endif

        @if($togglePassword)
            <input type="password" class="js-toggle-password form-control form-control-lg {{ $inputClass }} {{ $class }}"
                   name="{{ $name }}" id="{{ $name }}"
                   placeholder="{{ $placeholder }}" value="{{ $value ?? old($name) }}"
                   autocomplete="{{ $autocomplete ?? 'off' }}" {{ $required ? 'required' : '' }}
                   aria-label="{{ $placeholder }}"
                   data-msg="{{ $dataMsg ?? trans('messages.invalid_input') }}"
                   data-hs-toggle-password-options='{
                       "target": "#changePassTarget{{ $name }}",
                       "defaultClass": "tio-hidden-outlined",
                       "showClass": "tio-visible-outlined",
                       "classChangeTarget": "#changePassIcon{{ $name }}"
                   }'>
            <div id="changePassTarget{{ $name }}" class="input-group-append position-absolute right-0 top-0 h-100">
                <a class="input-group-text bg-transparent border-0" href="javascript:;">
                    <i id="changePassIcon{{ $name }}" class="tio-visible-outlined"></i>
                </a>
            </div>
        @else
            <input type="{{ $type }}" class="form-control form-control-lg {{ $inputClass }} {{ $class }}"
                   name="{{ $name }}" id="{{ $name }}"
                   placeholder="{{ $placeholder }}" value="{{ $value ?? old($name) }}"
                   autocomplete="{{ $autocomplete ?? 'off' }}" {{ $required ? 'required' : '' }}
                   aria-label="{{ $placeholder }}"
                   data-msg="{{ $dataMsg ?? trans('messages.invalid_input') }}">
        @endif
    </div>

    @if($errors->has($name))
        <div class="invalid-feedback d-block mt-1">{{ $errors->first($name) }}</div>
    @endif
</div>

@if($togglePassword)
    @push('scripts')
    <script>
        $(document).on('ready', function () {
            $('.js-toggle-password#{{ $name }}').each(function () {
                new HSTogglePassword(this).init()
            })
        })
    </script>
    @endpush
@endif
