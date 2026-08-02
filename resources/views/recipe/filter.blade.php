{{ html()->form('GET')->open() }}
    <div class="row pl-2">
        <div class="form-group col-md-3">
            {{ html()->label(__('message.category'), 'category_id')->class('form-control-label') }}
            {{ html()->select('category_id', $categories->pluck('title', 'id')->prepend(__('message.all_data'), '')->toArray(), request('category_id'))
                ->class('form-control select2js')
                ->attribute('id', 'category_filter')
            }}
        </div>
        <div class="form-group col-md-3">
            {{ html()->label(__('message.tag'), 'tag_id')->class('form-control-label') }}
            {{ html()->select('tag_id', $tags->pluck('title', 'id')->prepend(__('message.all_data'), '')->toArray(), request('tag_id'))
                ->class('form-control select2js')
                ->attribute('id', 'tag_filter')
            }}
        </div>
        <div class="form-group col-md-3">
            {{ html()->label(__('message.ingredient'), 'ingredient')->class('form-control-label') }}
            {{ html()->text('ingredient', request('ingredient'))
                ->class('form-control')
                ->attribute('placeholder', __('pagination.search'))
            }}
        </div>
        <div class="form-group col-md-3 mt-2">
            <button class="btn btn-sm btn-primary text-white mt-3 pt-2 pb-2">{{ __('message.apply_filter') }}</button>
            <a href="{{ route('recipe.index') }}" class="mr-1 btn btn-sm btn-danger mt-3 pt-2 pb-2">{{ __('message.reset_filter') }}</a>
        </div>
    </div>
{{ html()->form()->close() }}
