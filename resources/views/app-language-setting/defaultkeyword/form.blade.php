@if(isset($id))
    {{ html()->modelForm($data, 'PATCH', route('defaultkeyword.update', $id) )->open() }}
@else
    {{ html()->form('POST', route('defaultkeyword.store'))->open() }} 
@endif
<div class="row">
    <div class="form-group col-md-12">
        {{ html()->label(__('message.keyword_id') . ' <span class="text-danger">*</span>', 'keyword_id')->class('form-control-label') }}
        {{ html()->number('keyword_id')
            ->value(isset($id) ? $data->keyword_id : $lastKeywordId)
            ->placeholder(__('message.keyword_id'))
            ->class('form-control')
            ->attribute('required', 'required')
            ->attribute('readonly', 'readonly')
        }}
    </div>
    <div class="form-group col-md-12">
        {{ html()->label(__('message.keyword_title') . ' <span class="text-danger">*</span>', 'keyword_name')->class('form-control-label') }}
        @if(isset($id))
            <p>{{ $data->keyword_name }}</p>
        @else
        {{ html()->text('keyword_name')
            ->placeholder(__('message.keyword_title'))
            ->class('form-control')
            ->attribute('required', 'required')
        }}
        @endif
    </div>
    <div class="form-group col-md-12">
        {{ html()->label(__('message.keyword_value') . ' <span class="text-danger">*</span>', 'keyword_value')->class('form-control-label') }}
        {{ html()->text('keyword_value')
            ->placeholder(__('message.keyword_value'))
            ->class('form-control')
            ->attribute('required', 'required')
        }}
    </div>
    <div class="form-group col-md-12">
        {{ html()->label(__('message.screen_name') . ' <span class="text-danger">*</span>', 'screen_id')->class('form-control-label') }}
        {{ html()->select('screen_id', isset($id) ? [optional($data->screen)->screenId => optional($data->screen)->screenName] : [], old('screen_id'))
            ->class('select2 form-group')
            ->id('screenName')
            ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.screen_name')]))
            ->attribute('data-ajax--url', route('ajax-list', ['type' => 'screen']))
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
<script>
    $('#screenName').select2({
        dropdownParent: $('#formModal'),
        width: '100%',
        placeholder: "{{ __('message.select_name', ['select' => __('message.screen_name')]) }}",
    });
</script>
