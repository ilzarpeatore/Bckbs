{{ html()->form('POST', route('saveLangContent'))->attribute('data-toggle', 'validator')->open() }} 
    <input type="hidden" value="{{ $filename }}" name="filename"/>
    <input type="hidden" value="{{ $requestLang }}" name="requestLang"/>
    <input type="hidden" value="{{ $type }}" name="type"/>
    <div class="table-responsive mb-0">
        <table class="table language_table table-sm table-fixed">
            <thead>
                <tr>
                    <th scope="col">{{ __('message.key') }}</th>
                    <th scope="col">{{ __('message.value') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($flatArray as $key => $val)
                   <tr>
                       <td>{{ $val }} <small>({{ $key }})</small></td>
                       <td><input class="form-control" name="{{ $key }}" value="{{ $val }}" /></td>
                   </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <hr>
{{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
@if(isset($id))
{{ html()->closeModelForm() }}
@else
{{ html()->form()->close() }}
@endif
