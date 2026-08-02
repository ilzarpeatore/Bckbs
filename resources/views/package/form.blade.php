@push('scripts')
    <script>
        (function($) {
            $(document).ready(function(){
                tinymceEditor('.tinymce-description',' ',function (ed) {
                }, 450)

            });
        })(jQuery);
    </script>
@endpush

<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('package.update', $id) )->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('package.store'))->attribute('enctype', 'multipart/form-data')->open() }} 
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('package.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.name') . ' <span class="text-danger">*</span>', 'name')->class('form-control-label') }}
                                {{ html()->text('name')->placeholder(__('message.name'))->class('form-control')->attribute('required','required') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.duration_unit'))->class('form-control-label') }}
                                {{ html()->select('duration_unit',[ 'monthly' => __('message.monthly'), 'yearly' => __('message.yearly') ], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.duration').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                {{ html()->select('duration', ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', '11' => '11', '12' => '12' ], old('duration'))->class('form-control select2js')->attribute('required', 'required') ->id('duration') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.price').' <span class="text-danger">($)*</span>')->class('form-control-label') }}
                                {{ html()->number('price', old('price'))->class('form-control')->attribute('min', 0)->attribute('step', 'any')->attribute('required', 'required')->placeholder(__('message.price')) }}
                            </div>
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                {{ html()->select('status',[ 'active' => __('message.active'), 'inactive' => __('message.inactive') ], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                            </div>
                            <div class="form-group col-md-12">
                                {{ html()->label(__('message.description'))->class('form-control-label') }}
                                {{ html()->textarea('description', null)->class('form-control tinymce-description')->placeholder(__('message.description')) }}
                                
                            </div>
                        </div>
                        <hr>
                        {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
                    </div>
                </div>
            </div>
        </div>
        @if(isset($id))
            {{ html()->closeModelForm() }}
        @else
            {{ html()->form()->close() }}
        @endif
    </div>
</x-app-layout>
