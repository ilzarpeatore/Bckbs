{{ html()->form('GET')->open() }}
    <div class="row">
        <div class="form-group col-md-3">
            {{ html()->label(__('message.select_name', ['select' => __('message.screen')]), 'screen')->class('form-control-label') }}
            {{ html()->select('screen', isset($screen) ? [$screen->screenId => $screen->screenName] : [], old('screen'))
                ->class('select2Clear form-group screen')
                ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.screen')]))
                ->attribute('data-ajax--url', route('ajax-list', ['type' => 'screen']))
            }}
        </div>
        <div class="form-group col-md-2 mt-1"> 
            <button class="btn btn-primary text-white mt-4 pt-2">{{ __('message.apply_filter') }}</button>
        </div>
    </div>
@if(isset($id))
    {{ html()->closeModelForm() }}
@else
    {{ html()->form()->close() }}
@endif
