{{ html()->form('POST', route('settingUpdate'))->attribute('data-toggle', 'validator')->open() }}
 {{-- {{ html()->hidden('id',$setting_value->id ?? null) }} --}}
{{ html()->hidden('page', $page)->class('form-control') }}
    <div class="row">
        @foreach($setting as $key => $value)
            <div class="col-md-12 col-sm-12 card shadow mb-10">
                <div class="card-header">
                    <h4>{{ str_replace('_',' ',$key) }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($value as $sub_keys => $sub_value)
                            @php
                                $data=null;
                                foreach($setting_value as $v){

                                    if($v->key==($key.'_'.$sub_keys)){
                                        $data = $v;
                                    }
                                }
                                $class = 'col-md-6';
                                $type = 'text';
                                switch ($key){
                                    case 'FIREBASE':
                                        $class = 'col-md-12';
                                        break;
                                    case 'COLOR' : 
                                        $type = 'color';
                                        break;
                                    case 'DISTANCE' :
                                        $type = 'number';
                                        break;
                                    default : break;
                                }
                            @endphp
                            <div class=" {{ $class }} col-sm-12">
                                <div class="form-group">
                                    <label for="{{ $key.'_'.$sub_keys }}">{{ str_replace('_',' ',$sub_keys) }} {!! $sub_keys == 'TIME' ? '<span data-toggle="tooltip" title="'.__('message.set_quote_time', ['timezone' => $timezone ]).'">&#9432;</span>' : '' !!}</label>
                                    {{ html()->hidden('type[]', $key)->class('form-control') }}
                                    <input type="hidden" name="key[]" value="{{ $key.'_'.$sub_keys }}">
                                    @if($key == 'CURRENCY' && $sub_keys == 'CODE')
                                        @php
                                            $currency_code = $data->value ?? 'USD';
                                            $currency = currencyArray($currency_code);
                                        @endphp
                                        <select class="form-control select2js" name="value[]" id="{{ $key.'_'.$sub_keys }}">
                                            @foreach(currencyArray() as $array)
                                                <option value="{{ $array['code'] }}" {{ $array['code'] == $currency_code  ? 'selected' : '' }}> ( {{$array['symbol']  }}  ) {{ $array['name'] }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($key == 'CURRENCY' && $sub_keys == 'POSITION')
                                        {{ html()->select('value[]', [ 'left' => __('message.left'),'right' => __('message.right')], old('value[]', isset($data) ? $data->value : 'left'))->class('form-control select2js')->attribute('required', 'required')}}
                                    @elseif($key == 'QUOTE' && $sub_keys == 'TIME')
                                        <input type="{{ $type }}" name="value[]" value="{{ isset($data) ? $data->value : null }}" id="{{ $key.'_'.$sub_keys }}" class="form-control form-control-lg timepicker24" placeholder="{{ str_replace('_',' ',$sub_keys) }}">
                                    @elseif($key == 'APPVERSION')
                                        @if ($key == 'APPVERSION' && ( $sub_keys == 'ANDROID_FORCE_UPDATE' || $sub_keys == 'IOS_FORCE_UPDATE'))
                                            {{ html()->select('value[]', [ '0' => __('message.no'),'1' => __('message.yes')], old('value[]', isset($data) ? $data->value : '0'))->class('form-control select2js')}}
                                        @else
                                            <input type="{{ $type }}" name="value[]" value="{{ isset($data) ? $data->value : null }}" id="{{ $key.'_'.$sub_keys }}" {{ $type == 'number' ? "min=0 step='any'" : '' }} class="form-control form-control-lg" placeholder="{{ str_replace('_',' ',$sub_keys) }}">
                                        @endif
                                    @elseif ($key == 'AdsBannerDetail')
                                        <select class="form-control select2js" name="value[]">
                                            @foreach([1 => __('message.yes'), 0 => __('message.no')] as $value => $label)
                                                <option value="{{ $value }}" {{ old('value', $data->value ?? '1') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($sub_keys == 'ENABLE/DISABLE')
                                        <div class="custom-control custom-radio custom-control-inline col-5">
                                            {!! html()->radio('value[]', old('value[]', optional($data)->value) == 1, 1)
                                                ->class('custom-control-input')
                                                ->id('yes') !!}
                                            {!! html()->label(__('message.yes'))->for('yes')->class('custom-control-label') !!}
                                        </div>

                                        <div class="custom-control custom-radio custom-control-inline col-5">
                                            {!! html()->radio('value[]', old('value[]', optional($data)->value) == 0, 0)
                                                ->class('custom-control-input')
                                                ->id('no') !!}
                                            {!! html()->label(__('message.no'))->for('no')->class('custom-control-label') !!}
                                        </div>
                                    @elseif($key == 'MOBILE_GAME_ENABLE' && ( $sub_keys == 'TYPE' ))
                                        {!! html()->select('value[]', ['1' => __('message.yes'),'0' => __('message.no')],isset($data) ? $data->value : '0')->class('form-control select2js') !!}
                                    @else
                                        <input type="{{ $type }}" name="value[]" value="{{ isset($data) ? $data->value : null }}" id="{{ $key.'_'.$sub_keys }}" {{ $type == 'number' ? "min=0 step='any'" : '' }} class="form-control form-control-lg" placeholder="{{ str_replace('_',' ',$sub_keys) }}">
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <div class="col-md-12">
                            {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-md-end') }}
                        </div>
                    </div>
                </div>
            </div>
        @endForeach
    </div>
{{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-md-end') }}
@if(isset($id))
    {{ html()->closeModelForm() }}
@else
    {{ html()->form()->close() }}
@endif

<script>
    $(document).ready(function() {
        $('.select2js').select2();
        if($('.timepicker24').length > 0){
            flatpickr('.timepicker24', {
                enableTime: true,
                noCalendar: true,
                time_24hr: true,
                dateFormat: 'H:i',
            });
        }
    });
</script>
