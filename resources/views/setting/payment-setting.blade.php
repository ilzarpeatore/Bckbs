<div class="row">
    <div class="col-lg-12">
        <ul class="nav nav-payment-tabs" id="tab-text" role="tablist">
            @foreach(config('constant.PAYMENT_GATEWAY_SETTING') as $key => $value)
                <li class="nav-item">
                    <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=payment-setting&type={{$key}}" data-target=".paste_here" data-value="{{$key}}" id="pills-{{$key}}-tab-fill" data-toggle="tabajax" role="tab" class="nav-link {{ $key == $type ? 'active' : '' }}" aria-controls="pills-{{$key}}" aria-selected="{{ $key == $type ? true : false }}"> {{ __('message.'.$key) }}</a>
                </li>
            @endforeach
        </ul>
        <div class="card">
            <div class="card-body">
                <div class="tab-content" id="pills-tabContent-1">
                    @foreach(config('constant.PAYMENT_GATEWAY_SETTING') as $key => $value)
                    <div class="tab-pane fade {{ $key == $type ? 'active show' : '' }}" id="pills-{{$key}}-fill" role="tabpanel" aria-labelledby="pills-{{$key}}-tab-fill">
                        {{ html()->modelForm($payment_setting_data,'POST', route('paymentSettingsUpdate'))->attribute('data-toggle', 'validator')->attribute('enctype','multipart/form-data')->open() }}
                        {{ html()->hidden('id')->value(null)->class('form-control') }}
                        {{ html()->hidden('type')->value($key)->class('form-control') }}                        
                            <div class="row">
                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                    {{ html()->text('title')->value($payment_setting_data->title ?? '')->placeholder(__('message.title'))->class('form-control')->required() }}                                    
                                </div>

                                @if( $key != 'cash' )
                                <div class="form-group col-md-6">
                                    <label class="d-block">{{ __('message.mode') }} </label>
                                    <div class="custom-control custom-radio d-inline-block col-2">
                                        {{ html()->radio('is_test', old('is_test', $payment_setting_data->is_test ?? '1') == '1', '1')->class('form-check-input')->id('is_test_test_' . $key) }}
                                        {{ html()->label(__('message.test'), 'is_test_test_' . $key)->class('form-check-label') }}                                            
                                    </div>
                                    <div class="custom-control custom-radio d-inline-block col-2">
                                        {{ html()->radio('is_test', old('is_test', $payment_setting_data->is_test ?? '1') == '0', '0')->class('form-check-input')->id('is_test_live_' . $key) }}
                                        {{ html()->label(__('message.live'), 'is_test_live_' . $key)->class('form-check-label') }}                                            
                                    </div>
                                </div>                                
                                @endif
                            </div>
                            @if( $key != 'cash' )
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-3">{{ __('message.test') }}</h5>
                                    <hr>
                                    @if( is_array($value) )
                                        @foreach( $value as $val)
                                            <div class="form-group">
                                                {{ html()->label(__('message.' . $val) . ' <span class="text-danger">*</span>', 'test_value['.$val.']')->class('form-control-label') }}
                                                {{ html()->text('test_value['.$val.']')->value(old('test_value.' . $val, $payment_setting_data->test_value[$val] ?? ''))->placeholder(__('message.' . $val))->class('form-control') }}
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <h5 class="mb-3">{{ __('message.live') }}</h5>
                                    <hr>
                                    @if( is_array($value) )
                                        @foreach( $value as $val)
                                            <div class="form-group">
                                                {{ html()->label(__('message.' . $val), 'live_value[' . $val . ']')->class('form-control-label') }}
                                                {{ html()->text('live_value[' . $val . ']')->value(old('live_value.' . $val, $payment_setting_data->live_value[$val] ?? ''))->class('form-control')->placeholder(__('message.' . $val)) }}
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            @endif
                            @if( $key != 'cash' )
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                        {{ html()->select('status',[ '1' => __('message.active'), '0' => __('message.inactive') ], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="form-control-label" for="image">{{ __('message.image') }} </label>
                                        <div class="">
                                            <input class="form-control file-input" type="file" name="gateway_image" accept="image/*">
                                        </div>
                                    </div>
        
                                    @if( isset($payment_setting_data) && getMediaFileExit($payment_setting_data, 'gateway_image'))
                                    <div class="col-md-2 mb-2 position-relative">
                                        <img id="gateway_image_preview" src="{{ getSingleMedia($payment_setting_data,'gateway_image') }}" alt="gateway-image" class="avatar-100 mt-1">
                                        <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $payment_setting_data->id, 'type' => 'gateway_image']) }}"
                                            data--submit='confirm_form'
                                            data--confirmation='true'
                                            data--ajax='true'
                                            data-toggle='tooltip'
                                            title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                            data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                            data-message='{{ __("message.remove_file_msg") }}'
                                        >
                                            <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                                <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                            </svg>
                                        </a>
                                    </div>
                                        @else
                                        <div class="col-md-3 mb-3">
                                            <img src="{{ asset('images/'.$key.'.png') }}" alt="gateway-image" class="attachment-image mt-1">
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <hr>
                        {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
                        @if(isset($id))
                            {{ html()->closeModelForm() }}
                        @else
                            {{ html()->form()->close() }}
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>