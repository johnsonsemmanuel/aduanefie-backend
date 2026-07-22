@extends('layouts.admin.app')

@section('title', translate('messages.Storage_Connection'))


@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/captcha.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.storage_connection_credentials_setup')}}
                </span>
            </h1>
            @include('admin-views.business-settings.partials.third-party-links')
        </div>
        <!-- End Page Header -->
        <div class="card border-0">
            <div class="card-header card-header-shadow">
                <h5 class="card-title align-items-center">
                    {{translate('Storage_Connection_Settings')}}
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        @php($config=\App\CentralLogics\Helpers::get_business_settings('local_storage')??1)
                        <form action="{{route('admin.business-settings.third-party.storage_connection_update',['local_storage'])}}"
                              method="post" id="local_storage_status_form">
                            @csrf
                            <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                <span class="pr-1 d-flex align-items-center switch--label">
                                    <span class="line--limit-1">
                                        {{translate('Local Storage')}}
                                    </span>
                                    <span class="form-label-secondary text-danger d-flex" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('If_enabled_System_will_store_all_files_and_images_to_local_storage')}}"><img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="Veg/non-veg toggle"> * </span>
                                </span>
                                <input type="hidden" name="toggle_type" value="local_storage">
                                <input
                                    type="checkbox" id="local_storage_status"
                                    data-id="local_storage_status"
                                    data-type="status"
                                    data-image-on="{{ asset('/public/assets/admin/img/modal/local_storage.png') }}"
                                    data-image-off="{{ asset('/public/assets/admin/img/modal/local_storage.png') }}"
                                    data-title-on="{{ translate('By Turning ON Local Storage Option') }}"
                                    data-title-off="{{ translate('By Turning OFF Local Storage Option') }}"
                                    data-text-on="<p>{{ translate('System_will_store_all_files_and_images_to_local_storage') }}</p>"
                                    data-text-off="<p>{{ translate('System_will_not_store_all_files_and_images_to_local_storage') }}</p>"
                                    class="status toggle-switch-input dynamic-checkbox"
                                    name="status" value="1" {{$config?($config==1?'checked':''):''}}>
                                <span class="toggle-switch-label text">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </form>
                    </div>
                    <div class="col-md-4">
                        @php($config=\App\CentralLogics\Helpers::get_business_settings('3rd_party_storage'))
                        <form action="{{route('admin.business-settings.third-party.storage_connection_update',['3rd_party_storage'])}}"
                              method="post" id="3rd_party_storage_status_form">
                            @csrf
                            <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                <span class="pr-1 d-flex align-items-center switch--label">
                                    <span class="line--limit-1">
                                        {{translate('3rd Party Storage')}}
                                    </span>
                                    <span class="form-label-secondary text-danger d-flex" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('If_enabled_System_will_store_all_files_and_images_to_3rd_party_storage')}}"><img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="Veg/non-veg toggle"> * </span>
                                </span>
                                <input type="hidden" name="toggle_type" value="3rd_party_storage">
                                <input
                                    type="checkbox" id="3rd_party_storage_status"
                                    data-id="3rd_party_storage_status"
                                    data-type="status"
                                    data-image-on="{{ asset('/public/assets/admin/img/modal/3rd_party_storage.png') }}"
                                    data-image-off="{{ asset('/public/assets/admin/img/modal/3rd_party_storage.png') }}"
                                    data-title-on="{{ translate('By Turning ON 3rd Party Storage Option') }}"
                                    data-title-off="{{ translate('By Turning OFF 3rd Party Storage Option') }}"
                                    data-text-on="<p>{{ translate('System_will_store_all_files_and_images_to_3rd_party_storage') }}</p>"
                                    data-text-off="<p>{{ translate('System_will_not_store_all_files_and_images_to_3rd_party_storage') }}</p>"
                                    class="status toggle-switch-input dynamic-checkbox"
                                    name="status" value="1" {{$config?($config==1?'checked':''):''}}>
                                <span class="toggle-switch-label text">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        @php($config=\App\CentralLogics\Helpers::get_business_settings('s3_credential'))
        <div class="card mt-3">
            <div class="p-4 card-header-shadow">
                <h4 class="card-title align-items-center">
                    {{translate('Cloud_Storage_Credentials')}}
                </h4>
                <span>{{ translate('Configure_your_cloud_storage_provider_below.') }}</span>
            </div>
            <div class="card-body">
                <div class="mt-2 px-3">
                    <form
                        action="{{getEnvMode()!='demo'?route('admin.business-settings.third-party.storage_connection_update',['storage_connection']):'javascript:'}}"
                        method="post" id="storageConnectionForm">
                        @csrf
                                {{-- Provider Selector --}}
                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="provider" class="form-label">{{translate('Storage_Provider')}}</label>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <select id="storageProvider" name="provider" class="form-control mb-2" onchange="updateStorageHelp(this.value)">
                                                <option value="s3" {{($config['provider'] ?? 's3') === 's3' ? 'selected' : ''}}>{{translate('Amazon_S3')}}</option>
                                                <option value="r2" {{($config['provider'] ?? '') === 'r2' ? 'selected' : ''}}>{{translate('Cloudflare_R2')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="key" class="form-label">{{translate('messages.key')}} <span class="text-danger">*</span></label>
                                        <small class="text-muted" id="keyHelp"></small>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <input required id="key" type="text" class="form-control mb-2" name="key"
                                                   value="{{getEnvMode()!='demo'?$config['key']??"":''}}">
                                        </div>
                                    </div>
                                </div>
                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="secret" class="form-label">{{translate('messages.secret')}} <span class="text-danger">*</span></label>
                                        <small class="text-muted" id="secretHelp"></small>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <input required id="secret" type="text" class="form-control mb-2" name="secret"
                                                   value="{{getEnvMode()!='demo'?$config['secret']??"":''}}">
                                        </div>
                                    </div>
                                </div>
                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="region" class="form-label">{{translate('messages.region')}} <span class="text-danger">*</span></label>
                                        <small class="text-muted" id="regionHelp"></small>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <input required id="region" type="text" class="form-control mb-2" name="region"
                                                   value="{{getEnvMode()!='demo'?$config['region']??"":''}}">
                                        </div>
                                    </div>
                                </div>
                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="bucket" class="form-label">{{translate('messages.bucket')}} <span class="text-danger">*</span></label>
                                        <small class="text-muted" id="bucketHelp"></small>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <input required id="bucket" type="text" class="form-control mb-2" name="bucket"
                                                   value="{{getEnvMode()!='demo'?$config['bucket']??"":''}}">
                                        </div>
                                    </div>
                                </div>
                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="url" class="form-label">{{translate('messages.url')}} <span class="text-danger">*</span></label>
                                        <small class="text-muted" id="urlHelp"></small>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <input required id="url" type="text" class="form-control mb-2" name="url"
                                                   value="{{getEnvMode()!='demo'?$config['url']??"":''}}">
                                        </div>
                                    </div>
                                </div>
                                <div class="border pt-5 radius-10 row mb-3">
                                    <div class="col-lg-4 col-sm-6 p-10px">
                                        <label for="end_point" class="form-label">{{translate('messages.end_point')}} <span class="text-danger">*</span></label>
                                        <small class="text-muted" id="endPointHelp"></small>
                                    </div>
                                    <div class="col-lg-8 col-sm-6">
                                        <div class="form-group">
                                            <input required id="end_point" type="text" class="form-control mb-2" name="end_point"
                                                   value="{{getEnvMode()!='demo'?$config['end_point']??"":''}}">
                                        </div>
                                    </div>
                                </div>
                        <div class="btn--container justify-content-end">
                            <button type="button" class="btn btn--secondary" id="testConnectionBtn" onclick="testStorageConnection()">
                                <i class="tio-refresh"></i> {{translate('Test_Connection')}}
                            </button>
                            <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                            <button type="{{getEnvMode()!='demo'?'submit':'button'}}" class="btn btn--primary call-demo">{{translate('messages.save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const storageHelpTexts = {
            s3: {
                key: '{{ translate("AWS_Access_Key_ID") }}',
                secret: '{{ translate("AWS_Secret_Access_Key") }}',
                region: '{{ translate("e.g._us-east-1") }}',
                bucket: '{{ translate("S3_bucket_name") }}',
                url: '{{ translate("S3_bucket_URL_(e.g._https://bucket.s3.amazonaws.com)") }}',
                endPoint: '{{ translate("S3_endpoint_(optional)_e.g._https://s3.us-east-1.amazonaws.com)") }}'
            },
            r2: {
                key: '{{ translate("R2_Access_Key_ID_(from_Cloudflare_Dashboard_-_R2_-_Manage_R2_API_Tokens)") }}',
                secret: '{{ translate("R2_Secret_Access_Key_(shown_once_when_creating_the_API_token)") }}',
                region: '{{ translate("Set_to_auto_—_R2_does_not_use_AWS_regions") }}',
                bucket: '{{ translate("R2_bucket_name") }}',
                url: '{{ translate("R2_public_access_URL_(e.g._https://pub-hash.r2.dev_or_custom_domain)") }}',
                endPoint: '{{ translate("R2_endpoint:_https://account-id.r2.cloudflarestorage.com") }}'
            }
        };
        function updateStorageHelp(provider) {
            const t = storageHelpTexts[provider] || storageHelpTexts.s3;
            document.getElementById('keyHelp').textContent = t.key;
            document.getElementById('secretHelp').textContent = t.secret;
            document.getElementById('regionHelp').textContent = t.region;
            document.getElementById('bucketHelp').textContent = t.bucket;
            document.getElementById('urlHelp').textContent = t.url;
            document.getElementById('endPointHelp').textContent = t.endPoint;
            if (provider === 'r2') {
                const regionInput = document.getElementById('region');
                if (!regionInput.value || regionInput.value === 'us-east-1') regionInput.value = 'auto';
            }
        }
        updateStorageHelp(document.getElementById('storageProvider').value);

        function testStorageConnection() {
            const btn = document.getElementById('testConnectionBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="tio-refresh tio-spin"></i> {{ translate("Testing") }}...';
            const form = document.getElementById('storageConnectionForm');
            const formData = new FormData(form);
            formData.append('_token', '{{ csrf_token() }}');
            fetch('{{ route("admin.business-settings.third-party.storage_connection_test") }}', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    toastr.success('{{ translate("Connection_successful") }}');
                } else {
                    toastr.error(data.message || '{{ translate("Connection_failed") }}');
                }
            })
            .catch(() => toastr.error('{{ translate("Connection_failed") }}'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="tio-refresh"></i> {{ translate("Test_Connection") }}';
            });
        }
    </script>



@endsection
