@push('scripts')
    <script>
        (function($) {
            $(document).ready(function()
            {
                var resetSequenceNumbers = function() {
                    $("#table_list tbody tr").each(function(i) {
                        $(this).find('td:first').text(i + 1);
                    });
                };
                resetSequenceNumbers();
                $(".select2tagsjs").select2({
                    width: "100%",
                    tags: true
                });
                tinymceEditor('.tinymce-description',' ',function (ed) {

                }, 450)
                var row = 0;
                $('#add_button').on('click', function ()
                {
                    $(".select2tagsjs").select2("destroy");
                    var tableBody = $('#table_list').find("tbody");
                    var trLast = tableBody.find("tr:last");
                    
                    trLast.find(".removebtn").show().fadeIn(300);

                    var trNew = trLast.clone();
                    row = trNew.attr('row');
                    row++;

                    trNew.attr('id','row_'+row).attr('data-id',0).attr('row',row);
                    trNew.find('[type="hidden"]').val(0).attr('data-id',0);

                    trNew.find('[id^="workout_days_id_"]').attr('name',"workout_days_id["+row+"]").attr('id',"workout_days_id_"+row).val('');
                    trNew.find('[id^="exercise_ids_"]').attr('name',"exercise_ids["+row+"][]").attr('id',"exercise_ids_"+row).val('');
                    trNew.find('[id^="is_rest_no_"]').attr('name',"is_rest["+row+"]").attr('id',"is_rest_no_"+row).val('0');
                    trNew.find('[id^="is_rest_yes_"]').attr('name',"is_rest["+row+"]").attr('id',"is_rest_yes_"+row).val('1').prop('checked', false);

                    trNew.find('[id^="remove_"]').attr('id',"remove_"+row).attr('row',row);

                    trLast.after(trNew);
                    $(".select2tagsjs").select2({
                        width: "100%",
                        tags: true
                    });
                    resetSequenceNumbers();
                });

                $(document).on('click','.removebtn', function()
                {
                    var row = $(this).attr('row');
                    var delete_row  = $('#row_'+row);
                    // console.log(delete_row);
                    var check_exists_id = delete_row.attr('data-id');
                    var total_row = $('#table_list tbody tr').length;
                    var user_response = confirm("{{ __('message.delete_msg') }}");
                    if(!user_response) {
                        return false;
                    }

                    if(total_row == 1){
                        $(document).find('#add_button').trigger('click');
                    }
                    // console.log(check_exists_id);
                    if(check_exists_id != 0 ) {
                        $.ajax({
                            url: "{{ route('workoutdays.exercise.delete')}}",
                            type: 'post',
                            data: {'id': check_exists_id, '_token': $('input[name=_token]').val()},
                            dataType: 'json',
                            success: function (response) {
                                if(response['status']) {
                                    delete_row.remove();
                                    showMessage(response.message);
                                } else {
                                    errorMessage(response.message);
                                }
                            }
                        });
                    } else {
                        delete_row.remove();
                    }
                    
                    resetSequenceNumbers();
                })
            });
            function showMessage(message) {
                Swal.fire({
                    icon: 'success',
                    title: "{{ __('message.done') }}",
                    text: message,
                    confirmButtonColor: "var(--bs-primary)",
                    confirmButtonText: "{{ __('message.ok') }}"
                });
            }

            function errorMessage(message) {
                Swal.fire({
                    icon: 'error',
                    title: "{{ __('message.opps') }}",
                    text: message,
                    confirmButtonColor: "var(--bs-primary)",
                    confirmButtonText: "{{ __('message.ok') }}"
                });
            }
        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('workout.update', $id) )->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('workout.store'))->attribute('enctype', 'multipart/form-data')->open() }} 
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('workout.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required','required') }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.level').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                {{ html()->select('level_id', isset($id) ? [ optional($data->level)->id => optional($data->level)->title ] : [], old('level_id'))
                                    ->class('select2js form-group level')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.level')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'level']))
                                    ->attribute('required', 'required') 
                                }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.workouttype').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                {{ html()->select('workout_type_id', isset($id) ? [ optional($data->workouttype)->id => optional($data->workouttype)->title ] : [], old('workout_type_id'))
                                    ->class('select2js form-group workouttype')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.workouttype')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'workout_type']))
                                    ->attribute('required', 'required') 
                                }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                {{ html()->select('status',[ 'active' => __('message.active'), 'inactive' => __('message.inactive') ], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                            </div>

                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="image">{{ __('message.image') }} </label>
                                <div class="">
                                    <input class="form-control file-input" type="file" name="workout_image" accept="image/*" id="image">
                                </div>
                            </div>
                            
                            @if( isset($id) && getMediaFileExit($data, 'workout_image'))
                                <div class="col-md-2 mb-2 position-relative">
                                    <img id="workout_image_preview" src="{{ getSingleMedia($data,'workout_image') }}" alt="workout-image" class="avatar-100 mt-1">
                                    <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $data->id, 'type' => 'workout_image']) }}"
                                        data--submit='confirm_form'
                                        data--confirmation='true'
                                        data--ajax='true'
                                        data-toggle='tooltip'
                                        title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                        data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                        data-message='{{ __("message.remove_file_msg") }}'
                                    >
                                        <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                            <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                        </svg>
                                    </a>
                                </div>
                            @endif
                            
                            <div class="form-group col-md-4">
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

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.is_premium'), 'is_premium-1')->class('form-control-label') }}
                                <div class="form-check">
                                    {!! html()->hidden('is_premium', 0)->class('form-check-input') !!}
                                    {!! html()->checkbox('is_premium', null, 1)->id('is_premium-1')->class('form-check-input') !!}
                                </div>
                            </div>

                            <div class="form-group col-md-12">
                                {{ html()->label(__('message.description'))->for('description')->class('form-control-label') }}
                                {{ html()->textarea('description', null)->class('form-control tinymce-description')->placeholder(__('message.description')) }}
                            </div>
                        </div>

                        <hr>
                        <h5 class="mb-3">{{__('message.workout_days')}} <button type="button" id="add_button" class="btn btn-sm btn-primary float-end">{{ __('message.add',['name' => '']) }}</button></h5>
                        <div class="row">
                            <div class="col-md-12">
                                <table id="table_list" class="table workout_days_table table-responsive">
                                    <thead>
                                        <tr>
                                            <th class="col-md-1">#</th>                                            
                                            <th class="col-md-3">{{ __('message.exercise') }}</th>
                                            <th class="col-md-3">{{ __('message.is_rest') }}</th>
                                            <th class="col-md-2">{{ __('message.action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @if(isset($id) && count($data->workoutDay) > 0)
                                        @foreach($data->workoutDay as $key => $field)
                                            <tr id="row_{{ $key }}" row="{{ $key }}" data-id="{{ $field->id }}">
                                                <td></td>
                                                <td>
                                                    <div class="form-group" id="exercise_ids_{{$key}}">
                                                        <input type="hidden" name="workout_days_id[{{$key}}]" class="form-control" value="{{ $field->id }}" id="workout_days_id_{{$key}}" />
                                                        <div class="form-group">
                                                            {{ html()->select('exercise_ids[' . $key . '][]', $field->exercise_data ?? [], old('exercise_ids[' . $key . ']', $field->exercise_ids ?? []))
                                                                ->class('select2tagsjs form-group exercise')
                                                                ->multiple('multiple')
                                                                ->id('exercise_ids_' . $key)
                                                                ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.exercise')]))
                                                                ->attribute('data-ajax--url', route('ajax-list', ['type' => 'exercise'])) 
                                                            }}
                                                        </div>
                                                        
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-group">
                                                        <input type="hidden" name="is_rest[{{$key}}]" value="0" id="is_rest_no_{{$key}}">
                                                        {{ html()->checkbox('is_rest[' . $key . ']', $field->is_rest ?? 0, $field->is_rest ?? null)->class('form-check-input')->id('is_rest_yes_' . $key) }}
                                                    </div>                                                    
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0)" id="remove_{{$key}}" class="removebtn btn btn-sm btn-icon btn-danger" row="{{$key}}">
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
                                                <div class="form-group" id="exercise_ids_0">
                                                    <input type="hidden" name="workout_days_id[0]" class="form-control" value="0" id="workout_days_id_0" />
                                                    {{ html()->select('exercise_ids[0][]', $exerciseOptions ?? [], old('exercise_ids[0]', []))
                                                        ->class('select2tagsjs form-group exercise')
                                                        ->multiple('multiple')
                                                        ->id('exercise_ids_0')
                                                        ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.exercise')]))
                                                        ->attribute('data-ajax--url', route('ajax-list', ['type' => 'exercise'])) 
                                                    }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-group">
                                                    <input type="hidden" name="is_rest[0]" value="0" id="is_rest_no_0">
                                                    {!! html()->checkbox('is_rest[0]', old('is_rest.0', 0), 1)
                                                    ->class('form-check-input')
                                                    ->id('is_rest_yes_1') !!}
                                                </div>
                                            </td>
                                            <td>
                                                <a href="javascript:void(0)" id="remove_0" class="removebtn btn btn-sm btn-icon btn-danger" row="0">
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
