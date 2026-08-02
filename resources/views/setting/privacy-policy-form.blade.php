@push('scripts')
    <script>
        (function($) {
            $(document).ready(function(){
                tinymceEditor('.tinymce-privacy_policy',' ',function (ed) {

                }, 450)
            
            });

        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-block card-stretch">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-between align-items-center p-3">
                        <h5 class="font-weight-bold">{{ $pageTitle ?? __('message.list') }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    {{ html()->form('POST', route('pages.privacy_policy_save'))->attribute('data-toggle', 'validator')->open() }}
                    {{ html()->hidden('id',$setting_data->id ?? null) }}
                        <div class="row">
                            <div class="form-group col-md-12">
                                {{ html()->label(__('message.privacy_policy'))->class('form-control-label')}}
                                {{ html()->textarea('value', old('value', $setting_data['value'] ?? ''))->class('form-control tinymce-privacy_policy')->placeholder(__('message.privacy_policy')) }}
                            </div>
                        </div>
                    {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
                    {{ html()->form()->close() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>