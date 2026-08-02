@if(isset($id))
    {{ html()->modelForm($data, 'PATCH', route('languagewithkeyword.update', $id) )->open() }}
@else
    {{ html()->form('POST', route('languagewithkeyword.store'))->open() }} 
@endif
    <div class="row">
        <div class="form-group col-md-12">
            {{ html()->label(__('message.language', ['select' => __('message.language')]), 'language')->class('form-control-label') }}
            {{ html()->select('language', isset($data) ? [$data->languagelist->id => optional($data->languagelist)->language_name] : [], old('language'))
                ->class('form-control select2 language')
                ->attribute('disabled', true)
            }}
        </div>
        <div class="form-group col-md-12">
            {{ html()->label(__('message.keyword_title', ['select' => __('message.keyword')]), 'keyword')->class('form-control-label') }}
            {{ html()->select('keyword', isset($data) ? [$data->defaultkeyword->id => optional($data->defaultkeyword)->keyword_name] : [], old('keyword'))
                ->class('form-control select2 keyword')
                ->attribute('disabled', true)
            }}
        </div>
        <div class="form-group col-md-12">
            {{ html()->label(__('message.keyword_value') . ' <span class="text-danger">*</span>', 'keyword_value')->class('form-control-label') }}
            {{ html()->text('keyword_value', old('keyword_value'))
                ->placeholder(__('message.keyword_value'))
                ->class('form-control')
                ->attribute('required', 'required')
            }}
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">{{ __('message.close') }}</button>
        <button type="submit" class="btn btn-md btn-primary" id="btn_submit" data-form="ajax" >{{ __('message.save') }}</button>
    </div>
@if(isset($id))
    {{ html()->closeModelForm() }}
@else
    {{ html()->form()->close() }}
@endif