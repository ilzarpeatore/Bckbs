@push('scripts')
    <style>
        .select2-results__option[aria-disabled="true"] {
            background-color: #f2f2f2 !important;
            color: #555 !important;
            cursor: not-allowed;
        }
        #wizardWizard .nav-link.disabled,
        #recipeWizard .nav-link.disabled {
            pointer-events: auto !important;
            cursor: pointer !important;
        }
    </style>
    <script>
        (function($) {
            tinymceEditor('.tinymce-description', ' ', function(ed) {}, 450)

            function resetSequence(tableId) {
                $(tableId + " tbody tr").each(function(i) {
                    $(this).find('td:first').text(i + 1);
                });
            }
            resetSequence('#recipe_step_table_list');
            // resetSequence('#ingredient_table_list');

            //Add Step
            $('#recipe_steps_add_button').on('click', function() {
                let trLast = $('#recipe_step_table_list tbody tr:last');
                let trNew = trLast.clone();
                let row = parseInt(trLast.attr('row')) + 1;
                trNew.attr({id: 'row_' + row,row: row,'data-id': 0});
                trNew.find('[name^="recipe_steps_id"]').attr('name', 'recipe_steps_id[' + row + ']').attr('id', 'recipe_steps_id[' + row + ']').val('');
                trNew.find('[name^="instruction"]').attr('name', 'instruction[' + row + ']').attr('id', 'instruction[' + row + ']').val('');
                trNew.find('.removestepbtn').attr({row: row,id: 'removecat_' + row});

                trLast.after(trNew);
                resetSequence('#recipe_step_table_list');
            });

            //Remove step
            $(document).on('click', '.removestepbtn', function() {
                if (!confirm("{{ __('message.delete_msg') }}")) return false;
                let rows = $('#recipe_step_table_list tbody tr');

                if (rows.length === 1) {
                    rows.find('input[name^="instruction"]').val('');
                    return false;
                }
                $('#row_' + $(this).attr('row')).remove();
            });

            //Add Ingredient
            $('#ingredient_add_button').on('click', function ()
            {
                var tableBody = $('#ingredient_table_list').find("tbody");
                var trLast = tableBody.find("tr:last");

                trLast.find(".removeingrebtn").show().fadeIn(300);

                var trNew = trLast.clone();

                // Advanced Clean up for Select2 and values
                trNew.find('.select2-container').remove();
                trNew.find('select').each(function() {
                    $(this).removeClass('select2-hidden-accessible')
                           .removeAttr('data-select2-id')
                           .removeAttr('aria-hidden')
                           .removeAttr('tabindex')
                           .empty() // Remove all cloned options
                           ; // Add fresh empty option
                });
                trNew.find('.ingredient-select').attr('data-density', 1.0);
                trNew.find('*').removeAttr('data-select2-id'); // Complete cleanup
                
                row = parseInt(trNew.attr('row')) + 1;

                trNew.attr('id','ingre_row_'+row).attr('data-id',0).attr('row',row);
                trNew.find('[id^="recipe_ingredient_id_"]').attr('name', "ingredients[" + row + "][recipe_ingredient_id]").attr('id', "recipe_ingredient_id_" + row).val('');
                trNew.find('[id^="ingredient_ids_"]').attr('name',"ingredients["+row+"][ingredient_id]").attr('id',"ingredient_ids_"+row).val('');
                trNew.find('[id^="measurement_unit_ids_"]').attr('name',"ingredients["+row+"][measurement_unit_id]").attr('id',"measurement_unit_ids_"+row).val('');
                trNew.find('[id^="quantity_ids_"]').attr('name',"ingredients["+row+"][quantity]").attr('id',"quantity_ids_"+row).attr('step','any').attr('min',0).val('');
                trNew.find('[id^="amount_ids_"]').attr('name',"ingredients["+row+"][amount]").attr('id',"amount_ids_"+row).attr('step','any').attr('min',0).val('');
                trNew.find('[id^="quantity_grams_ids_"]').attr('name',"ingredients["+row+"][quantity_grams]").attr('id',"quantity_grams_ids_"+row).attr('step','any').attr('min',0).val('');                    

                trNew.find('[id^="removeingre_"]').attr('id',"removeingre_"+row).attr('row',row);

                trLast.after(trNew);
                
                // Initialize only the new row's selects specifically
                initIngredientSelect(trNew.find('.ingredient-select'));
                trNew.find('.unit-select').select2({ width: '100%' });
                
                // Clear all input values
                trNew.find('input').val('');
                
                // resetSequence('#ingredient_table_list');
            });
           
            //Remove Ingredient
            $(document).on('click', '.removeingrebtn', function() {
                if (!confirm("{{ __('message.delete_msg') }}")) return false;
                let rows = $('#ingredient_table_list tbody tr');
                if (rows.length === 1) {
                    rows.first().find('select').val(null).trigger('change');
                    rows.first().find('input').val('');
                    return false;
                }
                $('#ingre_row_' + $(this).attr('row')).remove();
                // resetSequence('#ingredient_table_list');
            });

            //Ingredient SELECT2
            function selectedIngredients() {
                return $('.ingredient-select').map(function() {
                    return $(this).val();
                }).get().filter(function(id) {
                    return id != null && id !== '';
                });
            }

            function initIngredientSelect(el) {
                $(el).select2({
                    width: '100%',
                    ajax: {
                        url: "{{ route('ajax-list', ['type' => 'ingredient']) }}",
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            let currentVal = $(el).val();
                            let excluded = selectedIngredients().filter(id => id != currentVal);
                            return {
                                q: params.term,
                                type: 'ingredient',
                                excluded_ids: excluded.join(',')
                            };
                        },
                        processResults: function(data) {
                            let used = selectedIngredients();
                            return {
                                results: data.results.map(function (item) {
                                    return {
                                        id: item.id,
                                        text: item.text,
                                        density: item.density,
                                        disabled: used.includes(String(item.id)) && String(item.id) !== String($(el).val())
                                    };
                                })
                            };
                        },
                        cache: true
                    }
                }).on('select2:select', function(e) {
                    let data = e.params.data;
                    $(this).find(':selected').attr('data-density', data.density || 1.0);
                    $(this).attr('data-density', data.density || 1.0);
                });
            }

            //Button control
            function toggleNextSaveButtons(state){
                $('.save-btn, .next-btn, .basic-details-save-btn').prop('disabled', state);
            }

            function updateWizardButtons() {
                let currentStep = $('#recipeWizard .nav-link.active').data('step') || 'recipe-basic-details';
                let isValid = validateStep(currentStep, true); // Silent validation
                toggleNextSaveButtons(!isValid);
            }
            
            //save button show
            function toggleSaveButton(){
                let isEditMode = "{{ isset($id) ? 1 : 0 }}";                
                if(isEditMode == 1){
                    $('.save-btn, .basic-details-save-btn').show();
                    updateWizardButtons();
                    return;
                }                
                if($('.wizard-step[data-step="recipe-basic-details"]').is(':visible')){                    
                    $('.basic-details-save-btn').hide();
                }else{                    
                    $('.save-btn').show();
                }
                updateWizardButtons();
            }

            function toIntFlag(value) {
                return parseInt(value, 10) === 1;
            }

            function isAmountReadOnlyOption(selectedOption) {
                if (!selectedOption || selectedOption.length === 0 || !selectedOption.val()) {
                    return false;
                }

                const amountReadOnly = selectedOption.data('amount-readonly') ?? selectedOption.attr('data-amount-readonly');
                return toIntFlag(amountReadOnly);
            }

            function isGramsReadOnlyOption(selectedOption) {
                if (!selectedOption || selectedOption.length === 0 || !selectedOption.val()) {
                    return false;
                }

                const gramsReadOnly = selectedOption.data('grams-readonly') ?? selectedOption.attr('data-grams-readonly');
                const hasConversion = selectedOption.data('has-conversion') ?? selectedOption.attr('data-has-conversion');
                return toIntFlag(gramsReadOnly) || toIntFlag(hasConversion);
            }

            //Ingredient change
            $(document).on('change', '.ingredient-select', function() {
                let row = $(this).closest('tr'),
                    unit = row.find('.unit-select'),
                    qty = row.find('.qty-input'),
                    amount = row.find('.amount-input'),
                    gram = row.find('.grams-input');

                unit.empty();
                qty.val('').removeData('old');
                amount.val('').prop('readonly', true);
                gram.val('').prop('readonly', false);

                if (!$(this).val()){
                    toggleNextSaveButtons(true);
                    return;
                }

                $.get("{{ route('ajax-list', ['type' => 'ingredient_units']) }}", {
                        ingredient_id: $(this).val()
                    },
                    function(res) {
                        let units = res.results ?? res;
                        if (!Array.isArray(units)) return;

                        units.forEach(u => {
                            let opt = new Option(u.text, u.id);
                            opt.setAttribute('data-gram', u.gram_equivalent || '');
                            opt.setAttribute('data-type', u.unit_type);
                            opt.setAttribute('data-standard', u.is_standard ? 1 : 0);
                            opt.setAttribute('data-has-conversion', u.has_conversion ? 1 : 0);
                            opt.setAttribute('data-amount-readonly', u.is_amount_readonly ? 1 : 0);
                            opt.setAttribute('data-grams-readonly', u.is_grams_readonly ? 1 : 0);
                            unit[0].add(opt);
                        });
                        
                        if(units.length === 1){
                            unit.val(units[0].id).trigger('change');
                            toggleNextSaveButtons(false);
                        }else if(units.length > 1){
                            unit.val(null).trigger('change');
                            toggleNextSaveButtons(true);
                        }else{
                            unit.val(null);
                            toggleNextSaveButtons(false);
                        }
                        unit.prop('required', units.length > 0);
                        unit.trigger('change.select2');
                    }
                );
                toggleSaveButton();
                updateWizardButtons();
            });

            //Unit Change
            $(document).on('change', '.unit-select', function() {
                var row = $(this).closest('tr');
                
                var amountInput = row.find('.amount-input'),
                    gramsInput = row.find('.grams-input'),
                    selectedOption = $(this).find(':selected'),
                    gramEquivalent = selectedOption.data('gram'),
                    isAmountReadOnly = isAmountReadOnlyOption(selectedOption),
                    isGramsReadOnly = isGramsReadOnlyOption(selectedOption);
                
                if (gramEquivalent) {
                    let finalGramEquivalent = parseFloat(gramEquivalent);
                    amountInput.val(finalGramEquivalent.toFixed(2));
                    
                    amountInput.prop('readonly', isAmountReadOnly);
                    gramsInput.prop('readonly', isGramsReadOnly);
                } else {
                    amountInput.val('').prop('readonly', true);
                    gramsInput.prop('readonly', false);
                }

                if (window.isInitializing) return; // Skip live calculation while loading edit data
                
                row.find('.qty-input').trigger('input');
                updateWizardButtons();
            });

            //QTY or Amount input change
            $(document).on('keydown', '.qty-input, .amount-input, .grams-input', function(e){
                if (e.key === '+' || e.key === '-') {
                    e.preventDefault();
                }
            });

            $(document).on('input', '.qty-input, .amount-input, .grams-input', function() {
                let row = $(this).closest('tr'),
                    qtyInput = row.find('.qty-input'),
                    amountInput = row.find('.amount-input'),
                    gramsInput = row.find('.grams-input'),
                    qty = parseFloat(qtyInput.val()),
                    amount = parseFloat(amountInput.val()),
                    grams = parseFloat(gramsInput.val()),
                    densitySelect = row.find('.ingredient-select'),
                    density = parseFloat(densitySelect.find(':selected').attr('data-density')) || parseFloat(densitySelect.attr('data-density')) || 1.0,
                    selectedOption = row.find('.unit-select').find(':selected'),
                    gramEquivalent = selectedOption.data('gram') || selectedOption.attr('data-gram'),
                    unitType = selectedOption.data('type') || selectedOption.attr('data-type'),
                    hasConversion = selectedOption.data('has-conversion') || selectedOption.attr('data-has-conversion');

                if (qty < 0 || amount < 0 || grams < 0) {
                    alert('{{__("message.positive_numbers_allowed")}}');
                    $(this).val('');
                    return;
                }

                let isLocked = (hasConversion == 1);

                if (gramEquivalent) {
                    if (isNaN(qty) || isNaN(amount)) {
                        if (isLocked) gramsInput.val('');
                        return;
                    }

                    // Force calculation from Amount to Grams ONLY if locked
                    // If not locked, we only calculate grams if QTY or Amount was edited
                    if (isLocked || $(this).hasClass('qty-input') || $(this).hasClass('amount-input')) {
                        // Apply density only when there is no ingredient-specific conversion.
                        let unitGramsValue = amount;
                        if (unitType === 'volume' && !isLocked) {
                            unitGramsValue *= density;
                        }
                        gramsInput.val((qty * unitGramsValue).toFixed(2));
                    }
                } else {
                    if (!isNaN(qty) && qty > 0 && !isNaN(grams) && grams > 0) {
                        // Amount remains readonly; do not back-calculate amount from grams.
                    } else if ($(this).hasClass('qty-input') || $(this).hasClass('grams-input')) {
                        // Amount remains readonly; do not clear/set based on grams input.
                    }
                }

                updateWizardButtons();
            });
          
           
            $(document).ready(function() {
                window.isInitializing = true;
                var requests = [];

                $('#ingredient_table_list tbody tr').each(function() {
                    let row = $(this),
                        ingredientId = row.find('.ingredient-select').val(),
                        unitId = row.find('.unit-select').val();
                    
                    if (!ingredientId) return;

                    let req = $.get("{{ route('ajax-list', ['type' => 'ingredient_units']) }}", {
                            ingredient_id: ingredientId
                        },
                        function(res) {
                            let units = res.results ?? res;
                            if (!Array.isArray(units)) return;
                            let unit = row.find('.unit-select');
                            unit.empty();
                            
                            // Add an empty option if no unit is selected
                            // if (!unitId) {
                            //     unit.append(new Option('', ''));
                            // }

                            units.forEach(u => {
                                let opt = new Option(u.text, u.id);
                                opt.setAttribute('data-gram', u.gram_equivalent || '');
                                opt.setAttribute('data-type', u.unit_type);
                                opt.setAttribute('data-standard', u.is_standard ? 1 : 0);
                                opt.setAttribute('data-has-conversion', u.has_conversion ? 1 : 0);
                                opt.setAttribute('data-amount-readonly', u.is_amount_readonly ? 1 : 0);
                                opt.setAttribute('data-grams-readonly', u.is_grams_readonly ? 1 : 0);
                                unit[0].add(opt);
                            });

                            if (unitId) {
                                row.find('.unit-select').val(unitId).trigger('change');
                            }
                        }
                    );
                    requests.push(req);
                });

                $.when.apply($, requests).always(function() {
                    $('.ingredient-select').each(function() {
                        initIngredientSelect($(this));
                    });
                    $(".unit-select").select2({
                        width: "100%"
                    });
                    
                    setTimeout(() => {
                        window.isInitializing = false;
                        // Trigger calculation for all rows once initialized to ensure consistency
                        $('.qty-input').trigger('input');
                        toggleSaveButton();
                    }, 500);
                });
            });

            // Handle ingredient change to refresh exclusion list without destroying all selects
            $(document).on('select2:select', '.ingredient-select', function() {
                // We don't need to destroy and re-init all selects here.
                // Select2 handles exclusion dynamically in processResults.
            });
            
            $(document).on('click', '[data--confirmation]', function () {
                setTimeout(function () {
                    $('.jconfirm-buttons button:last-child').remove();
                    $('.jconfirm-buttons button:first-child').text('{{__("message.ok")}}');
                }, 200);
            });

            //Form wizard
            let currentStep = 'recipe-basic-details';

            function showStep(step){
                $('.wizard-step').addClass('d-none');
                $('.wizard-step[data-step="'+step+'"]').removeClass('d-none');
                $('#recipeWizard .wizard-tab').removeClass('active').addClass('disabled');
                $('#recipeWizard .wizard-tab[data-step="'+step+'"]').addClass('active').removeClass('disabled');
            }
            
            function validateStep(step, silent = false){
                if(step === 'recipe-basic-details'){
                    let isValid = true;
                    $('.wizard-step[data-step="recipe-basic-details"]').find('input, select, textarea').each(function(){
                        if($(this).prop('required')){
                            let val = $(this).val();
                            if(!val || (Array.isArray(val) && val.length === 0)){
                                if(!silent) {
                                    this.reportValidity();
                                    $(this).focus();
                                }
                                isValid = false;
                                return false;
                            }
                        }
                    });
                    if(!isValid) return false;
                    if($('input[name="meal_type[]"]:checked').length === 0){
                        if(!silent) {
                            $('<a>').attr('data--confirmation','true').attr('data-title',"{{ __('message.add_meal_type') }}").attr('data-message',"{{ __('message.select_meal_type') }}").appendTo('body').trigger('click').remove();                                
                        }
                        return false;
                    }
                    return true;
                }
                if(step === 'recipe-ingredients'){                        
                    let hasIngredient = false;
                    let isValid = true;
                    let hasEmptyRow = false;
                    let ingredientValues = [];

                    $('#ingredient_table_list tbody tr').each(function(){
                        
                        let ingredient = $(this).find('select[name$="[ingredient_id]"]').val();                            
                        let qty  = $(this).find('input[name$="[quantity]"]').val();
                        let grams = $(this).find('input[name$="[quantity_grams]"]').val();
                        
                        if($(this).is(':visible')){
                            if(ingredient){
                                hasIngredient = true;

                                if(ingredientValues.includes(ingredient)){
                                    isValid = false;
                                    return false;
                                }
                                ingredientValues.push(ingredient);

                                if(!qty || !grams || parseFloat(grams) <= 0.01){
                                    hasEmptyRow = true;                                    
                                    return false;
                                }
                            }
                        }
                    });

                    if(!isValid){
                        if(!silent) {
                            currentStep = 'recipe-ingredients';
                            showStep(currentStep);
                            
                            $('<a>').attr('data--confirmation','true').attr('data-title',"{{ __('message.opps') }}").attr('data-message',"{{ __('message.duplicate_ingredient_not_allowed') }}").appendTo('body').trigger('click').remove();
                        }
                        return false;
                    }

                    if(!hasIngredient){
                        if(!silent) {
                            $('#recipeWizard .wizard-tab[data-step="recipe-ingredients"]').trigger('click');
                            $('<a>').attr('data--confirmation','true').attr('data-title',"{{ __('message.add_ingredient') }}").attr('data-message',"{{ __('message.add_atleast_one_ingredient') }}").appendTo('body').trigger('click').remove();
                        }
                        return false;
                    }
                    if(hasEmptyRow){
                        if(!silent) {
                            currentStep = 'recipe-ingredients';
                            showStep(currentStep);

                            $('<a>').attr('data--confirmation','true').attr('data-title',"{{ __('message.opps') }}").attr('data-message',"{{ __('message.enter_qty_grams')}}").appendTo('body').trigger('click').remove();

                        }
                        return false;
                    }                        
                    return true; 
                }
                if(step === 'recipe-preparation-step'){
                    return true; // Preparation steps are optional
                }
                return true;
            }

            $(document).ready(function(){
                // Next Button
                $(document).on('click','.next-btn',function(){
                    if(!validateStep(currentStep)){
                        return false;
                    }
                    if(currentStep === 'recipe-basic-details'){
                        currentStep = 'recipe-ingredients';
                    }else if(currentStep === 'recipe-ingredients'){                    
                        currentStep = 'recipe-preparation-step';
                    }
                    showStep(currentStep);
                    updateWizardButtons();
                });

                // Previous Button
                $(document).on('click', '.prev-btn', function(){                    
                    if(currentStep === 'recipe-preparation-step'){
                        currentStep = 'recipe-ingredients';
                    }else if(currentStep === 'recipe-ingredients'){
                        currentStep = 'recipe-basic-details';
                    }
                    showStep(currentStep);
                    updateWizardButtons();
                });

                // Click on tab to change step                
                $(document).on('click', '#recipeWizard .wizard-tab', function (e) {
                    e.preventDefault();
                    let targetStep = $(this).data('step');                  
                    if (targetStep !== currentStep) {
                        if (!validateStep(currentStep)) {
                            return false;
                        }
                    }
                    currentStep = targetStep;
                    showStep(currentStep);
                });

                $(document).on('click', '.save-btn', function(e){
                    if(!validateStep('recipe-basic-details')){
                        currentStep = 'recipe-basic-details';
                        showStep(currentStep);
                        e.preventDefault();
                        return false;
                    }
                    if(currentStep === 'recipe-ingredients'){
                        if(!validateStep('recipe-ingredients')){
                            currentStep = 'recipe-ingredients';
                            showStep(currentStep);
                            e.preventDefault();
                            return false;
                        }
                    }
                });       

                $(document).on('input change', 'input, select, textarea', function() {
                    updateWizardButtons();
                });

                updateWizardButtons();
            });
        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null; ?>

        @if (isset($id))
            {{ html()->modelForm($data, 'PATCH', route('recipe.update', $id))->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('recipe.store'))->attribute('enctype', 'multipart/form-data')->open() }}
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('recipe.index') }}" class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <h5 class="text-danger mb-3">
                            <i><u>{{ __('message.notes') }}</u> :</i> {{ __('message.recipe_info') }}
                        </h5>
                        <p class="mb-3">
                            <button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#recipeSetupGuideModal">
                                {{ __('message.setup_guide') }}
                            </button>
                            <span class="text-muted">- {{ __('message.setup_guide_desc') }}</span>
                        </p>
                        <div class="modal fade" id="recipeSetupGuideModal" tabindex="-1" aria-labelledby="recipeSetupGuideModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="recipeSetupGuideModalLabel">{{ __('message.setup_guide_modal_title') }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('message.close') }}"></button>
                                    </div>
                                    <div class="modal-body">
                                        @php($setupGuide = __('message.setup_guide_modal_body'))
                                        @if (is_array($setupGuide))
                                            @if (!empty($setupGuide['intro']))
                                                <p>{{ $setupGuide['intro'] }}</p>
                                            @endif
                                            @foreach ($setupGuide['steps'] ?? [] as $step)
                                                @if (!empty($step['title']))
                                                    <h6>{{ $step['title'] }}</h6>
                                                @endif
                                                @if (!empty($step['items']) && is_array($step['items']))
                                                    <ul>
                                                        @foreach ($step['items'] as $item)
                                                            <li>{{ $item }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            @endforeach
                                        @else
                                            <p>{{ $setupGuide }}</p>
                                        @endif
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('message.close') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <ul class="nav nav-tabs gap-4" id="recipeWizard">
                            <li class="nav-item">
                                <a class="nav-link active wizard-tab" data-step="recipe-basic-details">{{ __('message.recipe_basic_details') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link disabled wizard-tab" data-step="recipe-ingredients">{{ __('message.ingredients') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link disabled wizard-tab" data-step="recipe-preparation-step">{{ __('message.recipe_preparation_step') }}</a>
                            </li>                            
                        </ul>

                        {{-- step-1 --}}
                        <div class="wizard-step" data-step="recipe-basic-details">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                    {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required', 'required') }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.preparation_time') . ' <span class="text-danger">*</span>', 'preparation_time')->class('form-control-label') }}
                                    {{ html()->number('preparation_time')->placeholder(__('message.preparation_time'))->class('form-control')->attribute('step', 'any')->attribute('min', 0)->attribute('required', 'required') }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.type') . ' <span class="text-danger">*</span>', 'type')->class('form-control-label') }}
                                    {{ html()->select('type', ['veg' => __('message.veg'), 'non-veg' => __('message.non_veg'), 'vegan' => __('message.vegan')], old('type'))->class('form-control select2js')->attribute('required', 'required') }}
                                </div>
                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.meal_type') . ' <span class="text-danger">*</span>', 'type')->class('form-control-label') }}
                                    <div class="d-flex flex-wrap gap-4 mt-2">
                                        @foreach (config('macro-nutrient.MEAL_TYPE') as $meal)
                                            <div class="custom-control custom-checkbox">
                                                {{ html()->checkbox('meal_type[]', in_array($meal, old('meal_type', $data->meal_type ?? [])), $meal)->class('form-check-input')->id('meal-' . $meal) }}
                                                <label class="custom-control-label" for="meal-{{ $meal }}">{{ __('message.' . $meal) }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.recipecategory') . ' <span class="text-danger">*</span>')->class('form-control-label') }}
                                    {{ html()->select('recipe_category_ids[]', $selected_recipecategory ?? [], isset($id) ? array_keys($selected_recipecategory ?? []) : [])->class('select2js form-group')->multiple()->attribute('required', true)->attribute('data-placeholder', __('message.select_name', ['select' => __('message.recipecategory')]))->attribute('data-ajax--url', route('ajax-list', ['type' => 'recipecategory'])) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.recipetag') . ' <span class="text-danger">*</span>')->class('form-control-label') }}
                                    {{ html()->select('recipe_tag_ids[]', $selected_recipetag ?? [], isset($id) ? array_keys($selected_recipetag ?? []) : [])->class('select2js form-group')->multiple()->attribute('required', true)->attribute('data-placeholder', __('message.select_name', ['select' => __('message.recipetag')]))->attribute('data-ajax--url', route('ajax-list', ['type' => 'recipetag'])) }}
                                </div>

                                <div class="form-group col-md-4">
                                    <label class="form-control-label" for="recipe_image">{{ __('message.image') }}</label>
                                    <div class="">
                                        <input class="form-control file-input" type="file" name="recipe_image" accept="image/*" id="recipe_image">
                                    </div>
                                </div>

                                @if (isset($id) && getMediaFileExit($data, 'recipe_image'))
                                    <div class="col-md-2 mb-2 position-relative">
                                        <img id="recipe_image_preview" src="{{ getSingleMedia($data, 'recipe_image') }}" alt="recipe-image" class="avatar-100 mt-1">
                                        <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $data->id, 'type' => 'recipe_image']) }}" data--submit='confirm_form' data--confirmation='true' data--ajax='true' data-toggle='tooltip' title='{{ __('message.remove_file_title', ['name' => __('message.image')]) }}' data-title='{{ __('message.remove_file_title', ['name' => __('message.image')]) }}' data-message='{{ __('message.remove_file_msg') }}'>
                                            <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                                <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                            </svg>
                                        </a>
                                    </div>
                                @endif

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                    {{ html()->select('status', ['active' => __('message.active'), 'inactive' => __('message.inactive')], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                                </div>

                                <div class="form-group col-md-12">
                                    {{ html()->label(__('message.description'))->for('description')->class('form-control-label') }}
                                    {{ html()->textarea('description', null)->class('form-control tinymce-description')->placeholder(__('message.description')) }}
                                </div>
                            </div>
                            <div>
                                {{ html()->submit(__('message.save'))->class('btn btn-md btn-primary float-end basic-details-save-btn ms-2') }}                                
                                {{ html()->button(__('message.next'))->type('button')->class('btn btn-outline-secondary next-btn float-end') }}                              
                            </div>
                        </div>

                        {{-- step-2 --}}
                        <div class="wizard-step d-none" data-step="recipe-ingredients">

                            <div class="row">
                                <div class="col-md-12 table-responsive">
                                    <table id="ingredient_table_list" class="table table-responsive">
                                        <thead>
                                            <tr>
                                                {{-- <th class="">#</th> --}}
                                                <th class="col-md-3">{{ __('message.ingredient') }}</th>
                                                <th class="col-md-3">{{ __('message.measurement_units') }}</th>
                                                <th class="col-md-2">{{ __('message.qty') }}</th>
                                                <th class="col-md-2 d-none">{{ __('message.amount') }}</th>
                                                <th class="col-md-2">{{ __('message.grams') }}</th>
                                                <th class="col-md-1">{{ __('message.action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (!empty($data->recipeIngredients) && $data->recipeIngredients->count() > 0)
                                                @foreach ($data->recipeIngredients as $key => $row)
                                                    <tr id="ingre_row_{{ $key }}" row="{{ $key }}" data-id="{{ $row->id }}">
                                                        {{ html()->hidden('ingredients['.$key.'][recipe_ingredient_id]', $row->id)->id('recipe_ingredient_id_'.$key) }}
                                                        {{-- <td></td> --}}
                                                        <td>{{ html()->select('ingredients['.$key.'][ingredient_id]',[$row->ingredient_id => optional($row->ingredient)->title],$row->ingredient_id)->class('select2ingredient-unit ingredient-select')->id('ingredient_ids_' . $key)->attribute('data-placeholder', __('message.ingredient'))->attribute('data-ajax--url', route('ajax-list', ['type' => 'ingredient']))->attribute('data-density', optional($row->ingredient)->density ?? 1.0) }}</td>
                                                        <td>{{ html()->select('ingredients['.$key.'][measurement_unit_id]', $row->measurement_unit_id ? [$row->measurement_unit_id => ($row->measurement_unit->title ?? '')] : [], $row->measurement_unit_id)->class('form-control unit-select select2ingredient-unit')->id('measurement_unit_ids_' . $key)->attribute('data-gram', $row->gram_equivalent) }}</td>
                                                        <td>{{ html()->number('ingredients['.$key.'][quantity]')->class('form-control qty-input')->id('quantity_ids_' . $key)->value($row->quantity)->placeholder(__('message.qty'))->attribute('step', 'any')->attribute('min', 0) }}</td>                                                        
                                                        <td class="d-none">{{ html()->number('ingredients['.$key.'][amount]')->class('form-control amount-input')->id('amount_ids_' . $key)->value($row->amount)->placeholder(__('message.amount'))->attribute('step', 'any')->attribute('min', 0) }}</td>
                                                        <td>{{ html()->number('ingredients['.$key.'][quantity_grams]')->class('form-control grams-input')->id('quantity_grams_ids_' . $key)->value($row->quantity_grams)->placeholder(__('message.gram'))->attribute('step', 'any')->attribute('min', '0.01') }}</td>                         
                                                        <td>
                                                            <a href="javascript:void(0)" id="removeingre_{{ $key }}" class="removeingrebtn btn btn-sm btn-icon btn-danger" row="{{ $key }}">
                                                                <span class="btn-inner">
                                                                    <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                        <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                        <path d="M20.708 6.23975H3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                        <path d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    </svg>
                                                                </span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                {{-- CREATE MODE --}}
                                                <tr id="ingre_row_0" row="0" data-id="0">
                                                    {{ html()->hidden('ingredients[0][recipe_ingredient_id]', null)->id('recipe_ingredient_id_0') }}
                                                    {{-- <td></td> --}}
                                                    <td>{{ html()->select('ingredients[0][ingredient_id]')->class('form-control select2ingredient-unit ingredient-select')->id('ingredient_ids_0')->attribute('data-ajax--url', route('ajax-list', ['type' => 'ingredient']))->attribute('data-placeholder', __('message.ingredient')) }}</td>                                                    
                                                    <td>{{ html()->select('ingredients[0][measurement_unit_id]')->class('form-control unit-select select2ingredient-unit')->id('measurement_unit_ids_0') }}</td>                                                  
                                                    
                                                    <td>{{ html()->number('ingredients[0][quantity]')->class('form-control qty-input')->id('quantity_ids_0')->placeholder(__('message.qty'))->attribute('step', 'any')->attribute('min', 0) }}</td>
                                                    <td class="d-none">{{ html()->number('ingredients[0][amount]')->class('form-control amount-input')->id('amount_ids_0')->placeholder(__('message.amount'))->attribute('step', 'any')->attribute('min', 0) }}</td>
                                                    <td>{{ html()->number('ingredients[0][quantity_grams]')->class('form-control grams-input')->id('quantity_grams_ids_0')->placeholder(__('message.gram'))->attribute('step', 'any')->attribute('min', '0.01') }}</td>
                                                    <td>
                                                        <a href="javascript:void(0)" id="removeingre_0" class="removeingrebtn btn btn-sm btn-icon btn-danger" row="0">
                                                            <span class="btn-inner">
                                                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                    <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M20.708 6.23975H3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </svg>
                                                            </span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            {{ html()->button(__('message.previous'))->type('button')->class('btn btn-secondary prev-btn') }}                            
                            {{ html()->submit(__('message.save'))->class('btn btn-md btn-primary float-end save-btn ms-2') }}
                            {{ html()->button(__('message.next'))->type('button')->class('btn btn-outline-secondary next-btn float-end') }}
                            <a href="javascript:void(0)" id="ingredient_add_button" class="btn btn-sm btn-outline-primary float-end me-2 mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                    <path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/>
                                </svg>
                                {{ __('message.add') }}
                            </a>
                        </div>

                        {{-- step-3 --}}
                        <div class="wizard-step d-none" data-step="recipe-preparation-step">
                            <div class="row">
                                <div class="col-md-12">
                                    <table id="recipe_step_table_list" class="table table-responsive">
                                        <thead>
                                            <tr>
                                                <th class="col-md-1">#</th>
                                                <th class="col-md-10">{{ __('message.instruction') }}</th>
                                                <th class="col-md-1">{{ __('message.action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (!empty($data->steps) && $data->steps->count() > 0)
                                                @foreach ($data->steps as $key => $recipe_steps)
                                                    <tr id="row_{{ $key }}" row="{{ $key }}" data-id="{{ $recipe_steps->id }}">
                                                        <td></td>
                                                        <td>
                                                            {{ html()->hidden('recipe_steps_id['.$key.']', $recipe_steps->id) }}
                                                            {{ html()->text('instruction['.$key.']')->class('form-control')->value(old('instruction.' . $key, $recipe_steps->instruction ?? ''))->placeholder(__('message.instruction')) }}                                                            
                                                        </td>
                                                        <td>
                                                            <a href="javascript:void(0)" id="removecat_{{ $key }}" class="removestepbtn btn btn-sm btn-icon btn-danger" row="{{ $key }}">
                                                                <span class="btn-inner">
                                                                    <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                        <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                        <path d="M20.708 6.23975H3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                        <path d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    </svg>
                                                                </span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr id="row_0" row="0" data-id="0">
                                                    <td></td>
                                                    <td>
                                                        {{ html()->hidden('recipe_steps_id[0]', null) }}
                                                        {{ html()->text('instruction[0]')->class('form-control instruction')->placeholder(__('message.instruction')) }}                                                       
                                                    </td>
                                                    <td>
                                                        <a href="javascript:void(0)" id="removecat_0" class="removestepbtn btn btn-sm btn-icon btn-danger" row="0">
                                                            <span class="btn-inner">
                                                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                    <path d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M20.708 6.23975H3.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </svg>
                                                            </span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div>
                                {{ html()->button(__('message.previous'))->type('button')->class('btn btn-secondary prev-btn') }}
                                {{ html()->submit(__('message.save'))->class('btn btn-md btn-primary float-end save-btn ms-2') }}
                                <a href="javascript:void(0)" id="recipe_steps_add_button" class="btn btn-sm btn-outline-primary float-end me-2 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                                        <path d="M440-440H200v-80h240v-240h80v240h240v80H520v240h-80v-240Z"/>
                                    </svg>
                                    {{ __('message.add') }}
                                </a>
                            </div>
                        </div>                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if (isset($id))
        {{ html()->closeModelForm() }}
    @else
        {{ html()->form()->close() }}
    @endif
    </div>
</x-app-layout>
