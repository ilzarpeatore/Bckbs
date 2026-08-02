 {{ html()->form('POST', route('envSetting'))->attribute('data-toggle', 'validator')->open() }} 
    <div class="col-md-12 mt-20">
        <div class="row">

            {{ html()->hidden('id')->value(null)->class('form-control') }}
            {{ html()->hidden('page')->value($page)->class('form-control') }}
            {{ html()->hidden('type')->value('mail')->class('form-control') }}            
            @foreach(config('constant.MAIL_SETTING') as $key => $value)
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-control-label text-capitalize">{{ strtolower(str_replace('_',' ',$key)) }}</label>
                        @if(auth()->user()->hasRole('admin'))
                            <input type="{{$key=='MAIL_PASSWORD'?'password':'text'}}" value="{{ $value }}" name="ENV[{{$key}}]" class="form-control" placeholder="{{ config('constant.MAIL_PLACEHOLDER.'.$key) }}">
                        @else
                            <input type="{{$key=='MAIL_PASSWORD'?'password':'text'}}" value="" name="ENV[{{$key}}]" class="form-control" placeholder="{{ config('constant.MAIL_PLACEHOLDER.'.$key) }}">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
<hr>
{{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}

@if(isset($id))
    {{ html()->closeModelForm() }}
@else
    {{ html()->form()->close() }}
@endif