<!-- Modal -->
{{ html()->form('POST', route('save.assignworkout'))->attribute('data-toggle', 'validator')->open() }} 
    <div class="row">
    {{ html()->hidden('user_id',$user_id) }}
        <div class="form-group col-md-12">
            {{ html()->label(__('message.visibility'))->class('form-control-label') }}
            <div class="form-check">
                <div class="custom-control custom-radio d-inline-block col-4">
                    <label class="form-check-label" for="visibility-public">{{ __('message.public') }}</label>
                    {{ html()->radio('visibility', old('visibility') == 'public' || true, 'public')->class('form-check-input')->id('visibility-public')}}
                </div>
                <div class="custom-control custom-radio d-inline-block col-4">
                    <label class="form-check-label" for="visibility-private">{{ __('message.private') }}</label>
                    {{ html()->radio('visibility', old('visibility') == 'private', 'private')->class('form-check-input')->id('visibility-private') }}
                </div>
            </div>
        </div>

        <div class="form-group col-md-12">
            {{ html()->label(__('message.workout').' <span class="text-danger">*</span>')
            ->class('form-control-label') }}
            {{ html()->select('workout_id', [], old('workout_id'))
                ->class('select2js form-group workout_id')
                ->attribute('required', 'required') }}
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
(function () {
    let workout_defaultVisibility = $('input[name="visibility"]:checked').val() || 'public';
    loadWorkoutSelect(workout_defaultVisibility);

    $(document).on('change', 'input[name="visibility"]', function(){
        loadWorkoutSelect($(this).val());
    });

    function loadWorkoutSelect(visibility = 'public') {
        let workoutId = $('.select2js.workout_id');
        let submitBtn = $('#btn_submit');
        workoutId.empty().trigger('change');
        submitBtn.prop('disabled', true); 
        let workout_baseUrl = "{{ route('ajax-list', ['type' => 'workout', 'user_id' => $user_id, 'visibility' => '']) }}" + visibility;        
        let workout_route = workout_baseUrl.replaceAll('amp;', '');

        $.ajax({
            url: workout_route,
            method: "GET",
            cache: false,
            success: function (data) {
                workoutId.select2({
                    placeholder: "{{ __('message.select_name', ['select' => __('message.workout')]) }}",
                    dropdownParent: $('#formModal'),
                    width: '100%',
                    data: data.results
                });
                if (data.results && data.results.length > 0) {
                    let firstWorkoutId = data.results[0].id;
                    workoutId.val(firstWorkoutId).trigger('change');
                    submitBtn.prop('disabled', false);
                } 
                else {
                    submitBtn.prop('disabled', true);
                }
            }
        });
    }
})();
</script>

