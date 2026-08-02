{{ html()->form('POST', route('login.enable.setting.save'))->attribute('data-toggle', 'validator')->open() }} 
    {{ html()->hidden('id')->value(null)->class('form-control') }}
    {{ html()->hidden('page')->value($page)->class('form-control') }}
    <div class="row">
        <div class="col-lg-12">
            <div class="form-group">
                {{ html()->label(__('message.login_enable'))->class('form-control-label') }}

                <div class="form-check">
                    <div class="custom-control custom-radio d-inline-block col-4">
                        <label class="form-check-label" for="login_enable-no"> {{__('message.no')}} </label>
                        {{ html()->radio('login_enable', old('login_enable', $login_enable) == '0', '0')->class('form-check-input')->id('login_enable-no') }}
                    </div>
                    <div class="custom-control custom-radio d-inline-block col-4">
                        <label class="form-check-label" for="login_enable-yes"> {{__('message.yes')}} </label>
                        {{ html()->radio('login_enable', old('login_enable', $login_enable) == '1', '1')->class('form-check-input')->id('login_enable-yes') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
{{ html()->form()->close() }}
