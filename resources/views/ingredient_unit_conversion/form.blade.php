<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('ingredient-unit-conversion.update', $id))->open() }}
        @else
            {{ html()->form('POST', route('ingredient-unit-conversion.store'))->open() }}
        @endif

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('ingredient-unit-conversion.index') }}" class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info">
                            {{ __('message.conversion_setup_tips') }}
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.ingredient').' <span class="text-danger">*</span>', 'ingredient_id')->class('form-control-label') }}
                                {{ html()->select('ingredient_id', isset($id) ? [ optional($data->ingredient)->id => optional($data->ingredient)->title ] : [], old('ingredient_id'))
                                    ->class('select2js form-group ingredient')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.ingredient')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'ingredient']))
                                    ->attribute('required', 'required')
                                }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.measurement_unit').' <span class="text-danger">*</span>', 'measurement_unit_id')->class('form-control-label') }}
                                {{ html()->select('measurement_unit_id', isset($id) ? [ optional($data->measurementUnit)->id => optional($data->measurementUnit)->title ] : [], old('measurement_unit_id'))
                                    ->class('select2js form-group measurement_unit')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.measurement_unit')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'measurement_unit']))
                                    ->attribute('required', 'required')
                                }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.gram_equivalent').' <span class="text-danger">*</span>', 'gram_equivalent')->class('form-control-label') }}
                                {{ html()->number('gram_equivalent', old('gram_equivalent'))
                                    ->class('form-control')->attribute('min', 0)->attribute('step', 'any')
                                    ->attribute('required', 'required')->placeholder(__('message.gram_equivalent')) 
                                }}
                                <small class="text-muted">{{ __('message.gram_equivalent_help') }}</small>
                            </div>
                        </div>
                        <hr>
                        {{ html()->submit(__('message.save'))->class('btn btn-md btn-primary float-end') }}
                    </div>
                </div>
            </div>
        </div>

        @if(isset($id))
            {{ html()->closeModelForm() }}
        @else
            {{ html()->form()->close() }}
        @endif
    </div>
</x-app-layout>
