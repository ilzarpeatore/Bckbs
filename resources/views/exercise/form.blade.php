@push('scripts')
<script>
    (function ($) {
        $(document).ready(function () {
            tinymceEditor('.tinymce-instruction', ' ', function (ed) {}, 450)

            tinymceEditor('.tinymce-tips', ' ', function (ed) {}, 450)

            var video_type = $('select[name=video_type]').val();
            changeUploadFile(video_type);

            $(".video_type").change(function () {
                changeUploadFile(this.value)
            });

            $(".select2tagsjs").select2({
                width: "100%",
                tags: true
            });

            var row = 0;
            $('#add_button').on('click', function () {
                var tableBody = $('#table_list').find("tbody");
                var trLast = tableBody.find("tr:last");
                var trNew = trLast.clone();
                row = trNew.attr('row');
                row++;

                trNew.attr('id', 'row_' + row).attr('data-id', 0).attr('row', row);
                trNew.find('[type="hidden"]').val(0).attr('data-id', 0);
                trNew.find('[id^="remove_"]').attr('id', "remove_" + row).attr('row', row);

                trLast.after(trNew);
            });

            $(document).on('click', '.removebtn', function () {
                var row = $(this).attr('row');
                var delete_row = $('#row_' + row);

                var total_row = $('#table_list tbody tr').length;
                var user_response = confirm("{{ __('message.delete_msg') }}");
                if (!user_response) {
                    return false;
                }
                
                if (total_row == 1) {
                    $(document).find('#add_button').trigger('click');
                }
                delete_row.remove();
            })

            var type = "{{ old('type', isset($id) ? $data->type : 'sets') }}";
            changeTabValue(type)

            if( type == 'sets' ) {
                var based = "{{ old('based', isset($id) ? $data->based : 'reps') }}";
                
                toggleBasedColumns(based);
            }
            
            $('#exercise-pills-tab').on('show.bs.tab', function (e) { 
                changeTabValue($(e.target).attr('data-type'))
            });

            $(document).on('change', 'input[name="based"]', function () {
                toggleBasedColumns($(this).val());
            });
        });


        function changeTabValue(type) {
            $('input[name=type]').val(type);
        }

        function toggleBasedColumns(based) {
            if (based === 'time') {
                $('.reps-time-row').addClass('d-none');
            } else {
                $('.reps-time-row').removeClass('d-none');
            }
        }
        
        function changeUploadFile(type) {
            if (jQuery.inArray(type, ['url']) !== -1) {
                $('.video_url').removeClass('d-none');
                $('.video_upload').addClass('d-none');
            } else {
                $('.video_upload').removeClass('d-none');
                $('.video_url').addClass('d-none');
            }
        }
        if ($('.timepicker').length > 0) {
            flatpickr('.timepicker', {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i"
            });
        }

        $(document).on('click', '#equipment_clear', function () {
            $('.equipment').val(null).trigger('change');
            $('.duration_row').show();
            $('.normal_row').show();
            clearDurationSet();
        });
        
        $(document).on('click', '#sets_clear', function () {
            clearDurationSet('set');
        });

        $(document).on('click', '#duration_clear', function () {
            clearDurationSet('duration');
        });

        function clearDurationSet(type = null) {
            switch (type) {
                case 'duration':
                    $('#hours').val(null).trigger('change');
                    $('#minute').val(null).trigger('change');
                    $('#second').val(null).trigger('change');
                break;
                case 'set':
                    $('.normal_row').find('input').val('');
                break;
                default:
                    $('#hours').val(null).trigger('change');
                    $('#minute').val(null).trigger('change');
                    $('#second').val(null).trigger('change');
                    $('.normal_row').find('input').val('');
                break;
            }
        }

    })(jQuery);

</script>
@endpush

<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('exercise.update', $id) )->attribute('enctype', 'multipart/form-data')->open() }}
            {{html()->hidden('type', $data->type ?? null ) }}
        @else
            {{ html()->form('POST', route('exercise.store'))->attribute('enctype', 'multipart/form-data')->open() }} 
            {{html()->hidden('type', null) }}
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('exercise.index') }} " class="btn btn-sm btn-primary"
                                role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required','required') }}
                            </div>
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.bodypart').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                {{ html()->select('bodypart_ids[]', $selected_bodypart ?? [], isset($id) ? $data->bodypart_ids : [])
                                    ->class('select2js form-group exercise')
                                    ->multiple('multiple')
                                    ->attribute('required', 'required')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.bodypart')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'bodypart'])) 
                                }}
                            </div>                            
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.equipment').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                <a id="equipment_clear" class="float-end" href="javascript:void(0)">{{ __('message.l_clear') }}</a>
                                {{ html()->select('equipment_id', isset($id) ? [ optional($data->equipment)->id => optional($data->equipment)->title ] : [], old('equipment_id'))
                                    ->class('select2js form-group equipment')
                                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.equipment')]))
                                    ->attribute('data-ajax--url', route('ajax-list', ['type' => 'equipment']))
                                    ->attribute('required', 'required') 
                                }}
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
                                {{ html()->label(__('message.status') . ' <span class="text-danger">*</span>', 'status')->class('form-control-label') }}
                                {{ html()->select('status',[ 'active' => __('message.active'), 'inactive' => __('message.inactive') ], old('status'))->class('form-control select2js')->attribute('required', 'required') }}
                            </div>
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.is_premium'), 'is_premium-1')->class('form-control-label') }}
                                <div class="form-check">
                                    {!! html()->hidden('is_premium', 0)->class('form-check-input') !!}
                                    {!! html()->checkbox('is_premium', null, 1)->id('is_premium-1')->class('form-check-input') !!}
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.video_type').' <span class="text-danger">*</span>')->class('form-control-label') }}
                                {{ html()->select('video_type', ['url' => __('message.url'), 'upload_video' => __('message.upload_video')], old('video_type'))->class('form-control select2js video_type')->attribute('required', 'required')}}
                            </div>
                            <div class="form-group col-md-4 video_url">
                                {{ html()->label(__('message.video_url'), 'video_url')->class('form-control-label') }}
                                {{ html()->input('url','video_url', old('video_url'))->placeholder(__('message.video_url'))->class('form-control') }}                            
                            </div>

                            <div class="form-group col-md-4 video_upload">
                                <label class="form-control-label" for="exercise_video">{{ __('message.video') }}
                                </label>
                                <div class="">
                                    <input class="form-control file-input" type="file" name="exercise_video"
                                        accept="video/*" id="exercise_video" />
                                </div>
                            </div>
                            @if( isset($id) && getMediaFileExit($data, 'exercise_video'))
                            <div class="col-md-2 mb-2 position-relative">
                                <?php
                                        $file_extention = config('constant.IMAGE_EXTENTIONS');
                                        $image = getSingleMedia($data, 'exercise_video');
                                        
                                        $extention = in_array(strtolower(imageExtention($image)), $file_extention);
                                    ?>
                                @if($extention)
                                <img id="exercise_video_preview" src="{{ $image}}" alt="equipment-video"
                                    class="avatar-100 mt-1" />
                                @else
                                    <img id="exercise_video_preview" src="{{ asset('images/file.png') }}" class="avatar-100" />
                                    <a href="{{ $image }}" download>{{ __('message.download') }}</a>
                                @endif
                                <a class="text-danger remove-file"
                                    href="{{ route('remove.file', ['id' => $data->id, 'type' => 'exercise_video']) }}"
                                    data--submit='confirm_form' data--confirmation='true' data--ajax='true'
                                    data-toggle='tooltip'
                                    title='{{ __("message.remove_file_title" , ["name" =>  __("message.video") ]) }}'
                                    data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.video") ]) }}'
                                    data-message='{{ __("message.remove_file_msg") }}'>
                                    <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.4"
                                            d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z"
                                            fill="currentColor"></path>
                                        <path
                                            d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z"
                                            fill="currentColor"></path>
                                    </svg>
                                </a>
                            </div>
                            @endif

                        </div>

                        <div class="row ">
                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="image">{{ __('message.image') }} </label>
                                <div class="">
                                    <input class="form-control file-input" type="file" name="exercise_image" accept="image/*" id="image">
                                </div>
                            </div>
                            @if( isset($id) && getMediaFileExit($data, 'exercise_image'))
                            <div class="col-md-2 mb-2 position-relative">
                                <img id="exercise_image_preview" src="{{ getSingleMedia($data,'exercise_image') }}" alt="exercise-image" class="avatar-100 mt-1">
                                <a class="text-danger remove-file"
                                    href="{{ route('remove.file', ['id' => $data->id, 'type' => 'exercise_image']) }}"
                                    data--submit='confirm_form' data--confirmation='true' data--ajax='true'
                                    data-toggle='tooltip'
                                    title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                    data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                    data-message='{{ __("message.remove_file_msg") }}'>
                                    <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                        <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                    </svg>
                                </a>
                            </div>
                            @endif
                        </div>
                        @php
                        $duration = isset($id)&& $data->duration != null ? explode(':', $data->duration) : null;
                        $type = old('type', isset($id) && $data->type != null ? $data->type : 'sets');
                        $based = old('based', isset($id) && $data->based != null ? $data->based : 'reps');
                        @endphp
                        <h5 class="text-danger"> <i><u>{{ __('message.notes')}}:</u></i> {{ __('message.exercise_info') }}</h5>
                        <hr>
                        
                        <ul class="d-flex nav nav-pills nav-fill mb-3 text-center exercise-tab "  id="exercise-pills-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $type == 'sets' ? 'active show' : '' }}" data-bs-toggle="tab" href="#exercise-sets" data-type="sets" role="tab" aria-selected="{{ $type == 'sets' ? 'true' : 'false' }}">{{ __('message.sets') }}</a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $type == 'duration' ? 'active show' : '' }}" data-bs-toggle="tab" href="#exercise-duration" data-type="duration" role="tab" aria-selected="{{ $type == 'duration' ? 'true' : 'false' }}" tabindex="-1">{{ __('message.duration') }}</a>
                            </li>
                        </ul>
                        <div class="exercise-content tab-content">
                            <div id="exercise-sets" class="tab-pane fade {{ $type == 'sets' ? 'active show' : '' }}" role="tabpanel">
                                <div class="row normal_row">
                                    <div class="col-md-2">
                                        <h5 class="mb-3">{{__('message.sets')}} 
                                            <span class="text-danger" data-bs-toggle="tooltip" title="{{ __('message.exercise_sets_based_info') }}">
                                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M16.334 2.75H7.665C4.644 2.75 2.75 4.889 2.75 7.916V16.084C2.75 19.111 4.635 21.25 7.665 21.25H16.333C19.364 21.25 21.25 19.111 21.25 16.084V7.916C21.25 4.889 19.364 2.75 16.334 2.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path d="M11.9946 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path d="M11.9896 8.2041H11.9996" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                            </span>
                                        </h5>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <div class="custom-control custom-radio d-inline-block col-4">
                                                <label class="form-check-label" for="based-reps"> {{__('message.reps')}}(x)</label>
                                                {{ html()->radio('based', $based == 'reps', 'reps')->class('form-check-input')->id('based-reps') }}
                                            </div>
                                            <div class="custom-control custom-radio d-inline-block col-4">
                                                <label class="form-check-label" for="based-time"> {{__('message.time')}}(s)</label>
                                                {{ html()->radio('based', $based == 'time', 'time')->class('form-check-input')->id('based-time') }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group reps-time-row">
                                            <div class="form-group reps-time-row">
                                                <div class="input-group input-group-sm">
                                                    {{ html()->number('seconds_per_rep', old('seconds_per_rep'))->placeholder(__('message.seconds_per_rep'))->class('form-control')->attribute('min', 0) }}
                                                    <span class="input-group-text text-danger" data-bs-toggle="tooltip" title="{{ __('message.seconds_per_rep_help') }}">
                                                        <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.334 2.75H7.665C4.644 2.75 2.75 4.889 2.75 7.916V16.084C2.75 19.111 4.635 21.25 7.665 21.25H16.333C19.364 21.25 21.25 19.111 21.25 16.084V7.916C21.25 4.889 19.364 2.75 16.334 2.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M11.9946 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M11.9896 8.2041H11.9996" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <button type="button" id="add_button" class="btn btn-sm btn-primary float-end me-2">{{ __('message.add',['name' => '']) }}</button>
                                        <a id="sets_clear" class="float-end me-2" href="javascript:void(0)" title="{{ __('message.clear_sets') }}">{{ __('message.l_clear') }}</a> 
                                    </div>
                                    <div class="col-md-12">
                                        <table id="table_list" class="table table-responsive">
                                            <thead>
                                                <tr>
                                                    <th class="col-md-3">{{ __('message.reps') }}<span>(x)</span></th>
                                                    <th class="col-md-3">{{ __('message.time') }}(s)</th>
                                                    <th class="col-md-3 weight">{{ __('message.weight') }}<span>(kg)</span></th>
                                                    <th class="col-md-3">{{ __('message.rest') }}<span>(s)</span></th>
                                                    <th class="col-md-1">{{ __('message.action') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if(isset($id) && $data->sets != null && count($data->sets) > 0)
                                                @foreach($data->sets as $key => $field)

                                                <tr id="row_{{  $key }}" row="{{  $key }}" data-id="{{  $key }}">
                                                    <td>
                                                        <div class="form-group">
                                                            {{ html()->number('reps[]', $field['reps'] ?? null)->placeholder(__('message.reps'))->class('form-control')->attribute('min', 0) }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-group">
                                                            {{ html()->number('time[]', $field['time'] ?? null)->placeholder(__('message.time'))->class('form-control')->attribute('min', 0) }}
                                                        </div>
                                                    </td>
                                                    <td class="weight">
                                                        <div class="form-group">
                                                            {{ html()->number('weight[]', $field['weight'] ?? null)->placeholder(__('message.weight'))->class('form-control')->attribute('min', 0) }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-group">
                                                            {{ html()->number('rest[]', $field['rest'] ?? null)->placeholder(__('message.rest'))->class('form-control')->attribute('min', 0)}}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <a href="javascript:void(0)" id="remove_{{$key}}"
                                                            class="removebtn btn btn-sm btn-icon btn-danger" row="{{$key}}">
                                                            <span class="btn-inner">
                                                                <svg width="20" viewBox="0 0 24 24" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                    <path
                                                                        d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M20.708 6.23975H3.75" stroke="currentColor"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </svg>
                                                            </span>
                                                        </a>
                                                    </td>
                                                </tr>
                                                @endforeach
                                                @else
                                                <tr id="row_0" row="0" data-id="0">
                                                    <td>
                                                        <div class="form-group">
                                                            {{ html()->number('reps[]', old('reps'))->placeholder(__('message.reps'))->class('form-control')->attribute('min', 0) }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-group">
                                                            {{ html()->number('time[]', old('time'))->placeholder(__('message.time'))->class('form-control')->attribute('min', 0) }}
                                                        </div>
                                                    </td>
                                                    <td class="weight">
                                                        <div class="form-group">
                                                            {{ html()->number('weight[]', old('weight'))->placeholder(__('message.weight'))->class('form-control')->attribute('min', 0)}}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="form-group">
                                                            {{ html()->number('rest[]', old('rest'))->placeholder(__('message.rest'))->class('form-control')->attribute('min', 0) }}
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <a href="javascript:void(0)" id="remove_0"
                                                            class="removebtn btn btn-sm btn-icon btn-danger" row="0">
                                                            <span class="btn-inner">
                                                                <svg width="20" viewBox="0 0 24 24" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg" stroke="currentColor">
                                                                    <path
                                                                        d="M19.3248 9.46826C19.3248 9.46826 18.7818 16.2033 18.4668 19.0403C18.3168 20.3953 17.4798 21.1893 16.1088 21.2143C13.4998 21.2613 10.8878 21.2643 8.27979 21.2093C6.96079 21.1823 6.13779 20.3783 5.99079 19.0473C5.67379 16.1853 5.13379 9.46826 5.13379 9.46826"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M20.708 6.23975H3.75" stroke="currentColor"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M17.4406 6.23973C16.6556 6.23973 15.9796 5.68473 15.8256 4.91573L15.5826 3.69973C15.4326 3.13873 14.9246 2.75073 14.3456 2.75073H10.1126C9.53358 2.75073 9.02558 3.13873 8.87558 3.69973L8.63258 4.91573C8.47858 5.68473 7.80258 6.23973 7.01758 6.23973"
                                                                        stroke="currentColor" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
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
                            </div>
                            <div id="exercise-duration" class="tab-pane fade {{ $type == 'duration' ? 'active show' : '' }}" role="tabpanel">
                                <div class="row duration_row">
                                    <h5 class="mb-3">{{__('message.duration')}}
                                        <a id="duration_clear" class="float-end" href="javascript:void(0)" title="{{ __('message.clear_duration') }}">{{ __('message.l_clear') }}</a> 
                                    </h5>
                                    <div class="form-group col-md-2">
                                        {{ html()->label(__('message.hours').'  <span class="text-danger">*</span>')->class('form-control-label')}}
                                        {{ html()->select('hours', isset($duration) ? [ $duration[0] => $duration[0] ] : [], old('hours'))
                                            ->class('form-control select2js')
                                            ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.hours')]))
                                            ->attribute('data-ajax--url', route('ajax-list', ['type' => 'hours']))
                                        }}
                                    </div>
                                    
                                    <div class="form-group col-md-2">
                                        {{ html()->label(__('message.minute').' <span class="text-danger">*</span>')->class('form-control-label')}}
                                        {{ html()->select('minute', isset($duration) ? [$duration[1] => $duration[1]] : [], old('minute'))
                                            ->class('form-control select2js')
                                            ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.minute')]))
                                            ->attribute('data-ajax--url', route('ajax-list', ['type' => 'minute']))
                                        }}
                                    </div>
                                    <div class="form-group col-md-2">
                                        {{ html()->label(__('message.second').' <span class="text-danger">*</span>')->class('form-control-label')}}
                                        {{ html()->select('second', isset($duration) ? [$duration[2] => $duration[2]] : [], old('second'))
                                            ->class('form-control select2js')
                                            ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.second')]))
                                            ->attribute('data-ajax--url', route('ajax-list', ['type' => 'second']))
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="form-group col-md-12">
                            {{ html()->label(__('message.instruction'))->class('form-control-label')}}
                            {{ html()->textarea('instruction', null)->class('form-control tinymce-instruction')->placeholder(__('message.instruction')) }}
                        </div>
                        <div class="form-group col-md-12">
                            {{ html()->label(__('message.tips'))->class('form-control-label') }}
                            {{ html()->textarea('tips', null)->class('form-control tinymce-tips')->placeholder(__('message.tips')) }}
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
{{-- @push('scripts')
    <script>
         (function($) {
            $(document).ready(function() {
                $('.select2js').select2();
            });
        })(jQuery);
    </script>
@endpush --}}
</x-app-layout>
