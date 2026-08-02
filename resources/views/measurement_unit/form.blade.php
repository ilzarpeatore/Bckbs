<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>

        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('measurementunit.update', $id))->open() }}
        @else
            {{ html()->form('POST', route('measurementunit.store'))->attribute('data-toggle', 'validator')->open() }} 
        @endif

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('measurementunit.index') }}" class="btn btn-sm btn-primary" role="button">
                                {{ __('message.back') }}
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info">
                            {{ __('message.measurement_setup_tips') }}
                        </div>
                        <div class="row">

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required','required') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.symbol') . ' <span class="text-danger">*</span>', 'symbol')->class('form-control-label') }}
                                {{ html()->text('symbol')->placeholder(__('message.symbol'))->class('form-control')->attribute('required','required') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.unit_type') . ' <span class="text-danger">*</span>', 'unit_type')->class('form-control-label') }}
                                {{ html()->select(
                                    'unit_type',
                                    [
                                        'weight' => __('message.weight'),
                                        'volume' => __('message.volume'),
                                        'count'  => __('message.count'),
                                    ],
                                    old('unit_type')
                                )->class('form-control select2js')->attribute('required','required') }}
                            </div>

                             <div class="form-group col-md-4">
                                {{ html()->label(__('message.base_conversion_factor'), 'base_conversion_factor')->class('form-control-label') }}
                                {{ html()->number('base_conversion_factor', old('base_conversion_factor'))->class('form-control')->attribute('step', 'any')->placeholder('e.g. 1000 for kg, 1 for g, 240 for cup (ml)') }}
                                <small class="text-muted">{{ __('message.base_conversion_factor_help') }}</small>
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.is_standard'), 'is_standard_1')->class('form-control-label') }}
                                <div class="form-check">
                                    {{ html()->hidden('is_standard', 0) }}
                                    {{ html()->checkbox('is_standard', old('is_standard', $data->is_standard ?? 0) == 1, 1)->class('form-check-input')->id('is_standard_1') }}
                                </div>
                                <small class="text-muted">{{ __('message.is_standard_help') }}</small>
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                {{ html()->select('status', [ 'active' => __('message.active'), 'inactive' => __('message.inactive') ], old('status'))->class('form-control select2js')->attribute('required','required') }}
                            </div>
                        </div>

                        <hr>
                        {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
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
