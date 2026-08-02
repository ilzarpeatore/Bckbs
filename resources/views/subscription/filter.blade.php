{{ html()->form('GET')->open() }}
    <div class="row pl-2">
        <div class="form-group col-md-6">
            {{ html()->label(__('message.user').' <span class="text-danger">*</span>')->class('form-control-label') }}
            {{ html()->select('user_id[]', $users ?? [], $filter['user_id'] ?? [])
                ->class('select2js form-group')
                ->attribute('id','user_id_filter')
                ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.user')]))
                ->multiple('multiple')
            }}
        </div>
        <div class="form-group col-md-3">
            {{ html()->label(__('message.status'), 'status')->class('form-control-label') }}
            {{ html()->select('status',[ null => __('message.both'), 'active' => __('message.active'), 'inactive' => __('message.inactive'), 'expire_soon' => __('message.expire_soon') ], $filter['status'] ?? null)->class('form-control select2js') }}
        </div>        
        <div class="form-group col-md-3 mt-2"> 
            <button class="btn btn-sm btn-primary text-white mt-3 pt-2 pb-2">{{ __('message.apply_filter') }}</button>
            <a href="{{ route('subscription.index') }}" class="mr-1 btn btn-sm btn-danger mt-3 pt-2 pb-2">{{ __('message.reset_filter') }}</a>
        </div>
    </div>
{{ html()->form()->close() }}
