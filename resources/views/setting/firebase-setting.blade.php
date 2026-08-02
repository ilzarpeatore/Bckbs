{{ html()->form('POST', route('envSetting'))->attribute('data-toggle', 'validator')->open() }} 
    <div class="col-md-12 mt-20">
        <div class="row">
        {{ html()->hidden('id')->value(null)->class('form-control') }}
        {{ html()->hidden('page')->value($page)->class('form-control') }}         
        {{ html()->hidden('type')->value('firebase')->class('form-control') }}         
            @foreach(config('frontend.constant.FIREBASE_SETTING') as $key => $value)
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-control-label text-capitalize">{{ strtolower(str_replace('_',' ',$key)) }}</label>
                            <input type="text" value="{{ $value }}" name="ENV[{{$key}}]" class="form-control text-capitalize" placeholder="{{ strtolower(str_replace('_',' ',$key)) }}">
                    </div>
                </div>
            @endforeach
        </div>
    </div>
<hr>
{{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-md-end') }}
{{ html()->form()->close() }}
