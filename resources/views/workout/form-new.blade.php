@push('scripts')
    <script>
        (function($) {
            $(document).ready(function()
            {
                var resetSequenceNumbers = function() {
                    $("#ul_list ul li").each(function(i) {
                        $(this).find('span:first').text(i + 1 );
                    });
                };
                document.getElementById("search_days_data").style.display = "none";
               
                $('#days_data').click(function(event) {
                if($(event.target).is('#hide'))
                    $('#hide_days').show();
                else
                $('#search_days_data').show();
                $('#hide_days').hide();
                
            });
            
                $("#to-do li").click(Done);

                function Done() {
                    $(this).appendTo("#done");
                }
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
                    var tableBody = $('#ul_list').find("ul");
                    var trLast = tableBody.find("li:last");
                    
                    trLast.find(".removebtn").show().fadeIn(300);

                    var trNew = trLast.clone();
                    row = trNew.attr('row');
                    row++;

                    trNew.attr('id','row_'+row).attr('data-id',0).attr('row',row);
                    // trNew.find('[type="hidden"]').val(0).attr('data-id',0);

                    // trNew.find('[id^="workout_days_id_"]').attr('name',"workout_days_id["+row+"]").attr('id',"workout_days_id_"+row).val('');
                    // trNew.find('[id^="exercise_ids_"]').attr('name',"exercise_ids["+row+"][]").attr('id',"exercise_ids_"+row).val('');
                    // trNew.find('[id^="is_rest_no_"]').attr('name',"is_rest["+row+"]").attr('id',"is_rest_no_"+row).val('0');
                    // trNew.find('[id^="is_rest_yes_"]').attr('name',"is_rest["+row+"]").attr('id',"is_rest_yes_"+row).val('1').prop('checked', false);

                    // trNew.find('[id^="remove_"]').attr('id',"remove_"+row).attr('row',row);

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
                    var total_row = $('#ul_list tbody tr').length;
                    var user_response = confirm("Are you sure?");
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
                                showMessage(response.message);
                            }
                        });
                    }
                    delete_row.remove();
                    resetSequenceNumbers();
                })
                // $(document).ready(function() {
                //     $('#days_data').click(function() {
                //         $('#hide').hide();
                //         // $('#dayssdata').show();
                //     });
                //     $('#filter_data').show();
                   
                    
                // });
            });
        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('workout.update', $id))->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('workout.store'))->attribute('enctype','multipart/form-data')->open() }}  
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
                                {{ html()->text('title', old('title'))->placeholder(__('message.title'))->class('form-control')->required() }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.level') . ' <span class="text-danger">*</span>', 'level_id')->class('form-control-label') }}
                                {{ html()->select('level_id', isset($id) ? [optional($data->level)->id => optional($data->level)->title] : [], old('level_id'))
                                    ->class('select2js form-group level')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.level')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'level']))
                                    ->required() }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.workouttype') . ' <span class="text-danger">*</span>', 'workout_type_id')->class('form-control-label') }}
                                {{ html()->select('workout_type_id', isset($id) ? [optional($data->workouttype)->id => optional($data->workouttype)->title] : [], old('workout_type_id'))
                                    ->class('select2js form-group workouttype')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.workouttype')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'workout_type']))
                                    ->required() }}
                            </div>

                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                {{ html()->select('status', ['active' => __('message.active'), 'inactive' => __('message.inactive')], old('status'))->class('form-control select2js')->required() }}
                            </div>

                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="image">{{ __('message.image') }} </label>
                                <div class="">
                                    <input class="form-control file-input" type="file" name="workout_image" accept="image/*">
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
                                {{ html()->label(__('message.is_premium'), 'is_premium')->class('form-control-label') }}
                                <div>
                                    {{ html()->hidden('is_premium', 0) }}
                                    {{ html()->checkbox('is_premium', 1)->class('form-check-input') }}
                                    <label class="custom-control-label" for="is_premium"></label>
                                </div>
                            </div>

                            <div class="form-group col-md-12">
                                {{ html()->label(__('message.description'), 'description')->class('form-control-label') }}
                                {{ html()->textarea('description')->class('form-control tinymce-description')->placeholder(__('message.description')) }}
                            </div>
                        </div>

                        <hr>
                        <div class="row" >
                            <div class="card-body col-md-4" id="hide_days">
                              <h5 class="mb-3">{{__('message.days')}} <button type="button" id="add_button" class="btn btn-sm btn-primary float-end">{{ __('message.add',['name' => '']) }}</button></h5>
                                <div id="ul_list" class="">
                                        <ul class="list-inline m-0 p-0">
                                            <li class="d-flex mb-1 align-items-center" id="row_0" row="0" data-id="0">
                                                <span></span>
                                            </li>
                                        </ul>
                                 </div>
                            </div>
                            <div class="card-body col-md-4" id="search_days_data">
                                 <h5 class="mb-3">{{__('message.exercise')}}</h5>
                                    <ul class="list-inline m-0 p-0" id="to-do">
                                        @if( count($data['exercise']) > 0 )
											@foreach ($data['exercise'] as $exercise)
                                            <li class="d-flex mb-3 align-items-center" id="row_0" row="0" data-id="0">
                                                <img src="{{ getSingleMedia($exercise, 'exercise_image') }}" alt="exercise-image" class="bg-soft-primary rounded img-fluid avatar-40 me-3">
                                                <p>{{ $exercise->title }}</p>
                                            </li>
                                            @endforeach
										@else
												{{ __('message.not_found_entry', [ 'name' => __('message.exercise') ]) }}</td>
										@endif
                                    </ul>
                            </div>
                            <div class="card-body col-md-4" id="dayssdata">
                             <h5 class="mb-3">{{__('message.exercise')}} <button type="button" id="days_data" class="btn btn-sm btn-primary float-end">{{ __('message.add',['name' => '']) }}</button></h5>
                                    <ul class="list-inline m-0 p-0" id="done">
                                                   
                                    </ul>
                            </div>
                        </div>
                        <hr>
                        {{ html()->submit(__('message.send'))->class('btn btn-md btn-primary float-right') }}
                    </div>
                </div>
            </div>
        </div>
        {{ html()->form()->close() }}
    </div>
</x-app-layout>
