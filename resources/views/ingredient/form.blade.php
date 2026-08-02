@push('scripts')
    <script>
        (function ($) {
            $(document).on('click', '#ingredient_category_clear', function () {
                $('.ingredient_category').val(null).trigger('change');
            });
        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('ingredient.update', $id) )->open() }}
        @else
            {{ html()->form('POST', route('ingredient.store'))->open() }}
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('ingredient.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-info">
                            {{ __('message.ingredient_setup_tips') }}
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required','required') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.ingredient_category').' <span class="text-danger">*</span>','ingredient_category_id')->class('form-control-label') }}
                                <a id="ingredient_category_clear" class="float-end" href="javascript:void(0)">{{ __('message.l_clear') }}</a>
                                {{ html()->select('ingredient_category_id', isset($id) ? [ optional($data->ingredientcategory)->id => optional($data->ingredientcategory)->title ] : [], old('ingredient_category_id'))
                                    ->class('select2js form-group ingredient_category')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.ingredient_category')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'ingredient_category']))
                                    ->attribute('required', 'required')
                                }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                {{ html()->select('status',[ 'active' => __('message.active'), 'inactive' => __('message.inactive') ], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                            </div>

                        </div>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <div class="bg-light-primary rounded">
                                    <h6 class="mb-2">{{ __('message.macro_helper_title') }}</h6>
                                    <p class="small text-muted mb-3">{{ __('message.macro_helper_desc') }}</p>
                                    <div class="row">
                                    
                                        <div class="form-group col-md-3">
                                            {{ html()->label(__('message.calories').' <span class="text-danger">*</span>','calories')->class('form-control-label') }}
                                            {{ html()->number('calories', old('calories'))->class('form-control macro-helper-input')->attribute('data-target', 'calories_per_gram')->attribute('min', 0)->attribute('step', 'any')->attribute('required', 'required')->placeholder(__('message.calories')) }}
                                        </div>
                                        
                                        <div class="form-group col-md-3">
                                            {{ html()->label(__('message.protein'),'protein')->class('form-control-label') }}
                                            {{ html()->number('protein', old('protein'))->class('form-control macro-helper-input')->attribute('data-target', 'protein_per_gram')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.protein')) }}
                                        </div>
                                        
                                        <div class="form-group col-md-3">
                                            {{ html()->label(__('message.carbs'), 'carbs')->class('form-control-label') }}
                                            {{ html()->number('carbs', old('carbs'))->class('form-control macro-helper-input')->attribute('data-target', 'carbs_per_gram')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.carbs')) }}
                                        </div>

                                        <div class="form-group col-md-3">
                                            {{ html()->label(__('message.fat'), 'fat')->class('form-control-label') }}
                                            {{ html()->number('fat', old('fat'))->class('form-control macro-helper-input')->attribute('data-target', 'fat_per_gram')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.fat')) }}
                                        </div>

                                        <div class="form-group col-md-4">
                                            {{ html()->label(__('message.density'), 'density')->class('form-control-label') }}
                                            {{ html()->number('density', old('density'))->class('form-control')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.density')) }}
                                            <small class="text-muted">{{ __('message.density_help') }}</small>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="d-none">
                                <div class="form-group col-md-4">
                                    {{ html()->label(__('message.calories_per_gram').' <span class="text-danger">*</span>','calories_per_gram')->class('form-control-label') }}
                                    {{ html()->number('calories_per_gram', old('calories_per_gram'))->class('form-control')->attribute('min', 0)->attribute('step', 'any')->attribute('required', 'required')->placeholder(__('message.calories_per_gram')) }}
                                </div>
                                <div class="form-group col-md-4">
                                    {{ html()->label(__('message.protein_per_gram'),'protein_per_gram')->class('form-control-label') }}
                                    {{ html()->number('protein_per_gram', old('protein_per_gram'))->class('form-control')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.protein_per_gram')) }}
                                </div>
                                <div class="form-group col-md-4">
                                    {{ html()->label(__('message.fat_per_gram'), 'fat_per_gram')->class('form-control-label') }}
                                    {{ html()->number('fat_per_gram', old('fat_per_gram'))->class('form-control')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.fat_per_gram')) }}
                                </div>
                                <div class="form-group col-md-4">
                                    {{ html()->label(__('message.carbs_per_gram'), 'carbs_per_gram')->class('form-control-label') }}
                                    {{ html()->number('carbs_per_gram', old('carbs_per_gram'))->class('form-control')->attribute('min', 0)->attribute('step', 'any')->placeholder(__('message.carbs_per_gram')) }}
                                </div>
                            </div>
                        </div>
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
    @push('scripts')
        <script>
            $(document).on('input', '.macro-helper-input', function() {
                const val = $(this).val();
                const target = $(this).data('target');
                if (val !== '' && !isNaN(val)) {
                    $(`input[name="${target}"]`).val((parseFloat(val) / 100).toFixed(4));
                } else {
                    $(`input[name="${target}"]`).val('');
                }
            });
        </script>
    @endpush
</x-app-layout>
