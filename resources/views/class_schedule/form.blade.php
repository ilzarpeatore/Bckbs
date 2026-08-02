@push('scripts')
    <script>
        (function($) {
            $(document).ready(function(){
                changeValues();

                $('input[name="is_paid"]').change(function () {
                    changeValues();
                });

                $(document).on('change', '#workout_id', function () {                    
                    changeValues();
                });

                function changeValues() {
                    var is_paid_val = $('input[name="is_paid"]:checked').val();
                    if ( is_paid_val == 1 ) {
                        $('.is_paid_price').show();
                        $('#price').prop('required', true);
                    }else{
                        $('.is_paid_price').hide();
                        $('#price').prop('required', false);
                    }

                    var class_id = $('#workout_id').val();
                    if ( class_id == 'other' ) {
                        $('.workout_title').show();
                        $('#workout_title').prop('required', true);
                    }else{
                        $('.workout_title').hide();
                        $('#workout_title').prop('required', false);
                    }
                }
            });
        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('classschedule.update', $id) )->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('classschedule.store'))->attribute('enctype', 'multipart/form-data')->open() }} 
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('classschedule.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.class_name') . ' <span class="text-danger">*</span>', 'class_name')->class('form-control-label') }}
                                {{ html()->text('class_name')->placeholder(__('message.class_name'))->class('form-control')->attribute('required','required') }}
                            </div>
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.workout').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                {{ html()->select('workout_id', isset($id) ? $workout_id : [], old('workout_id'))
                                    ->class('select2js form-group workout')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.workout')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'workout', 'sub_type' => 'class_schedule_workout']))
                                }}

                            </div>
                            <div class="form-group col-md-4 workout_title">
                                {{ html()->label(__('message.workout_title') . ' <span class="text-danger">*</span>', 'workout_title')->class('form-control-label') }}
                                {{ html()->text('workout_title')->placeholder(__('message.workout_title'))->class('form-control')->attribute('required','required') }}
                            </div>
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.start_date').' <span class="text-danger">*</span>', 'start_date')->class('form-control-label') }}
                                {{ html()->text('start_date', old('start_date'))->class('maxdatepicker form-control')->placeholder(__('message.start_date'))->attribute('required', 'required') }}
                            </div>
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.end_date').' <span class="text-danger">*</span>', 'end_date')->class('form-control-label') }}
                                {{ html()->text('end_date', old('end_date'))->class('maxdatepicker form-control')->placeholder(__('message.end_date')) ->attribute('required', 'required') }}                                
                            </div>

                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.start_time').' <span class="text-danger">*</span>', 'start_time')->class('form-control-label') }}
                                {{ html()->text('start_time', old('start_time'))->class('timepicker24 form-control')->placeholder(__('message.start_time'))->attribute('required', 'required') }}                                
                            </div>
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.end_time').' <span class="text-danger">*</span>', 'end_time')->class('form-control-label') }}
                                {{ html()->text('end_time', old('end_time'))->class('timepicker24 form-control')->placeholder(__('message.end_time'))->attribute('required', 'required') }}

                            </div>
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.name'), 'name')->class('form-control-label') }}
                                {{ html()->text('name')->placeholder(__('message.name'))->class('form-control')->attribute('required','required') }}
                            </div>
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.link'), 'link')->class('form-control-label') }}
                                {{ html()->input('url', 'link', old('link'))
                                    ->placeholder(__('message.link'))
                                    ->class('form-control') }}
                            </div>
                            
                            
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.is_paid'))->class('form-control-label') }}
                                <div class="form-check">
                                    <div class="custom-control custom-radio d-inline-block col-4">
                                        <label class="form-check-label" for="is_paid-free"> {{ __('message.free') }} </label>
                                        {{ html()->radio('is_paid', old('is_paid', $data->is_paid ?? '0') == '0' || true, '0')
                                            ->class('form-check-input')
                                            ->id('is_paid-free') }}
                                    </div>
                                    <div class="custom-control custom-radio d-inline-block col-4">
                                        <label class="form-check-label" for="is_paid-paid"> {{ __('message.paid') }} </label>
                                        {{ html()->radio('is_paid', old('is_paid', $data->is_paid ?? '0') == '1', '1')
                                            ->class('form-check-input')
                                            ->id('is_paid-paid') }}
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-6 is_paid_price">
                                {{ html()->label(__('message.price').' <span class="text-danger">($)*</span>', 'price')->class('form-control-label') }}
                                {{ html()->number('price', old('price'))->class('form-control')->attribute('min',0)->attribute('step', 'any')->attribute('required', 'required')->placeholder(__('message.price')) }}
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
