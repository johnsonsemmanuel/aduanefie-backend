@php
    $language = \App\CentralLogics\Helpers::get_business_settings('language');
    $equipment = $equipment ?? null;
    $item = $equipment?->item;
@endphp

<div class="row g-2">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <div class="mb-20">
                    <h3 class="text-dark fs-18 mb-1">{{ translate('Basic Setup') }}</h3>
                    <p class="fs-12 mb-0">{{ translate('Provide the equipment name and a short description.') }}</p>
                </div>
                <div class="__bg-F8F9FC-card p-3">
                    <div class="js-nav-scroller hs-nav-scroller-horizontal">
                        <ul class="nav nav-tabs mb-4">
                            <li class="nav-item">
                                <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                            </li>
                            @foreach ($language ?? [] as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">
                                        {{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lang_form" id="default-form">
                        <div class="form-group">
                            <label class="input-label" for="default_name">{{ translate('Name') }} ({{ translate('Default') }}) <span class="text-danger">*</span></label>
                            <input type="text" name="name[]" id="default_name" class="form-control"
                                   value="{{ $item?->getRawOriginal('name') ?? old('name.0') }}"
                                   placeholder="{{ translate('e.g. Concrete Mixer') }}" required>
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                        <div class="form-group mb-0">
                            <label class="input-label" for="description-default">{{ translate('Description') }} ({{ translate('Default') }}) <span class="text-danger">*</span></label>
                            <textarea name="description[]" id="description-default" rows="4" maxlength="1000"
                                      class="form-control min-height-100px" required>{{ $item?->getRawOriginal('description') ?? old('description.0') }}</textarea>
                        </div>
                    </div>

                    @foreach ($language ?? [] as $key => $lang)
                        @php
                            $translatedName = '';
                            $translatedDescription = '';
                            if ($item && count($item->translations)) {
                                foreach ($item->translations as $translation) {
                                    if ($translation->locale === $lang && $translation->key === 'name') $translatedName = $translation->value;
                                    if ($translation->locale === $lang && $translation->key === 'description') $translatedDescription = $translation->value;
                                }
                            }
                        @endphp
                        <div class="d-none lang_form" id="{{ $lang }}-form">
                            <div class="form-group">
                                <label class="input-label" for="{{ $lang }}_name">{{ translate('Name') }} ({{ strtoupper($lang) }})</label>
                                <input type="text" name="name[]" id="{{ $lang }}_name" class="form-control" value="{{ $translatedName }}">
                            </div>
                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                            <div class="form-group mb-0">
                                <label class="input-label" for="description-{{ $lang }}">{{ translate('Description') }} ({{ strtoupper($lang) }})</label>
                                <textarea name="description[]" id="description-{{ $lang }}" rows="4" maxlength="1000" class="form-control">{{ $translatedDescription }}</textarea>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="mb-4">{{ translate('Thumbnail') }} <span class="text-danger">*</span></h3>
                <div class="__bg-F8F9FC-card d-center p-3">
                    @include('admin-views.partials._image-uploader', [
                        'id' => 'image-input',
                        'name' => 'image',
                        'ratio' => '1:1',
                        'isRequired' => $equipment ? false : true,
                        'existingImage' => $item?->image_full_url,
                        'imageExtension' => IMAGE_EXTENSION,
                        'imageFormat' => IMAGE_FORMAT,
                        'maxSize' => MAX_FILE_SIZE,
                        'textPosition' => 'bottom',
                    ])
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="mb-4">{{ translate('Categorization') }}</h3>
                <div class="form-group">
                    <label class="input-label" for="store_id">{{ translate('Store') }} <span class="text-danger">*</span></label>
                    <select name="store_id" id="store_id" class="form-control js-select2-custom" required>
                        <option value="">{{ translate('Select Store') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" data-module-id="{{ $store->module_id }}" {{ $item?->store_id == $store->id ? 'selected' : '' }}>
                                {{ $store->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="input-label" for="category_id">{{ translate('Category') }} <span class="text-danger">*</span></label>
                    <select name="category_id" id="category_id" class="form-control js-select2-custom" required>
                        <option value="">{{ translate('Select Category') }}</option>
                        @foreach ($categories ?? [] as $category)
                            <option value="{{ $category->id }}" {{ $item?->category_id == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="input-label" for="unit">{{ translate('Unit') }}</label>
                    <select name="unit" id="unit" class="form-control js-select2-custom">
                        <option value="">{{ translate('None') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" {{ $item?->unit_id == $unit->id ? 'selected' : '' }}>
                                {{ $unit->unit }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="input-label" for="stock">{{ translate('Available Units (Stock)') }} <span class="text-danger">*</span></label>
                    <input type="number" name="stock" id="stock" min="1" class="form-control" required
                           value="{{ $item?->stock ?? old('stock', 1) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="mb-4">{{ translate('Rental Rates') }} <span class="text-danger">*</span></h3>
                <p class="fs-12 text-muted">{{ translate('Set at least one rate. The lowest rate is shown as the starting price.') }}</p>
                <div class="form-group">
                    <label class="input-label" for="hourly_rate">{{ translate('Hourly Rate') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">{{ \App\CentralLogics\Helpers::currency_symbol() }}</span></div>
                        <input type="number" name="hourly_rate" id="hourly_rate" min="0" step="0.01" class="form-control"
                               value="{{ $equipment?->hourly_rate }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label" for="daily_rate">{{ translate('Daily Rate') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">{{ \App\CentralLogics\Helpers::currency_symbol() }}</span></div>
                        <input type="number" name="daily_rate" id="daily_rate" min="0" step="0.01" class="form-control"
                               value="{{ $equipment?->daily_rate }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label" for="weekly_rate">{{ translate('Weekly Rate') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">{{ \App\CentralLogics\Helpers::currency_symbol() }}</span></div>
                        <input type="number" name="weekly_rate" id="weekly_rate" min="0" step="0.01" class="form-control"
                               value="{{ $equipment?->weekly_rate }}">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="input-label" for="monthly_rate">{{ translate('Monthly Rate') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">{{ \App\CentralLogics\Helpers::currency_symbol() }}</span></div>
                        <input type="number" name="monthly_rate" id="monthly_rate" min="0" step="0.01" class="form-control"
                               value="{{ $equipment?->monthly_rate }}">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="mb-4">{{ translate('Rental Rules') }}</h3>
                <div class="form-group">
                    <label class="input-label" for="security_deposit">{{ translate('Security Deposit') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">{{ \App\CentralLogics\Helpers::currency_symbol() }}</span></div>
                        <input type="number" name="security_deposit" id="security_deposit" min="0" step="0.01" class="form-control"
                               value="{{ $equipment?->security_deposit ?? old('security_deposit', 0) }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="input-label" for="min_rental_duration">{{ translate('Minimum Rental Duration (hours)') }} <span class="text-danger">*</span></label>
                    <input type="number" name="min_rental_duration" id="min_rental_duration" min="1" class="form-control" required
                           value="{{ $equipment?->min_rental_duration ?? old('min_rental_duration', 1) }}">
                </div>
                <div class="form-group">
                    <label class="input-label" for="max_rental_duration">{{ translate('Maximum Rental Duration (hours)') }}</label>
                    <input type="number" name="max_rental_duration" id="max_rental_duration" min="1" class="form-control"
                           value="{{ $equipment?->max_rental_duration ?? old('max_rental_duration') }}">
                </div>
                <div class="form-group mb-0">
                    <label class="input-label" for="condition_notes">{{ translate('Condition Notes') }}</label>
                    <textarea name="condition_notes" id="condition_notes" rows="3" maxlength="2000" class="form-control">{{ $equipment?->condition_notes ?? old('condition_notes') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h3 class="mb-4">{{ translate('Service & Availability') }}</h3>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="self_pickup" value="1" class="custom-control-input" id="self_pickup"
                               {{ $equipment && !$equipment->self_pickup ? '' : 'checked' }}>
                        <label class="custom-control-label" for="self_pickup">{{ translate('Customer Self Pickup') }}</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="requires_delivery" value="1" class="custom-control-input" id="requires_delivery"
                               {{ $equipment?->requires_delivery ? 'checked' : '' }}>
                        <label class="custom-control-label" for="requires_delivery">{{ translate('Provider Offers Delivery') }}</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="operator_included" value="1" class="custom-control-input" id="operator_included"
                               {{ $equipment?->operator_included ? 'checked' : '' }}>
                        <label class="custom-control-label" for="operator_included">{{ translate('Operator Included') }}</label>
                    </div>
                </div>
                <div class="form-group {{ $equipment?->operator_included ? '' : 'd-none' }}" id="operator_fee_wrapper">
                    <label class="input-label" for="operator_fee">{{ translate('Operator Fee') }}</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">{{ \App\CentralLogics\Helpers::currency_symbol() }}</span></div>
                        <input type="number" name="operator_fee" id="operator_fee" min="0" step="0.01" class="form-control"
                               value="{{ $equipment?->operator_fee }}">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="input-label" for="status">{{ translate('Status') }} <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="available" {{ ($equipment?->status ?? 'available') === 'available' ? 'selected' : '' }}>{{ translate('Available') }}</option>
                        <option value="maintenance" {{ $equipment?->status === 'maintenance' ? 'selected' : '' }}>{{ translate('Under Maintenance') }}</option>
                        <option value="retired" {{ $equipment?->status === 'retired' ? 'selected' : '' }}>{{ translate('Retired') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script_2')
    <script>
        "use strict";
        $('#operator_included').on('change', function () {
            $('#operator_fee_wrapper').toggleClass('d-none', !$(this).is(':checked'));
        });

        $('.js-select2-custom').each(function () {
            let select2 = $.HSCore.components.HSSelect2.init($(this));
        });

        function loadCategories(moduleId, selectedId = null) {
            if (!moduleId) {
                $('#category_id').html('<option value="">{{ translate('Select Category') }}</option>');
                return;
            }
            $.ajax({
                url: '{{ route('admin.item.get-categories') }}',
                type: 'GET',
                data: { module_id: moduleId, parent_id: 0 },
                dataType: 'json',
                success: function (data) {
                    let options = '<option value="">{{ translate('Select Category') }}</option>';
                    data.forEach(function (category) {
                        let selected = String(category.id) === String(selectedId) ? 'selected' : '';
                        options += '<option value="' + category.id + '" ' + selected + '>' + category.text + '</option>';
                    });
                    $('#category_id').html(options);
                }
            });
        }

        $('#store_id').on('change', function () {
            let moduleId = $(this).find(':selected').data('module-id');
            loadCategories(moduleId);
        });

        @if ($item)
            loadCategories({{ $item->store?->module_id }}, {{ $item->category_id }});
        @endif
    </script>
@endpush
