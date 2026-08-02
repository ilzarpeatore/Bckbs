@push('scripts')
    <script>
        var globalFunctions = {};
        globalFunctions.ddInput = function(elem) {
            if ($(elem).length == 0 || typeof FileReader === "undefined") return;
            var $fileupload = $('input[type="file"]');
            var noitems = '<li class="no-items">{{ __('message.drop_your_template_here') }}<br><button class="blue-text">{{ __('message.browse_file') }}</button></li>';
            var hasitems = '<div class="browse hasitems">{{ __('message.other_file_to_upload') }} <span class="blue-text">Browse</span> {{ __('message.or_drop_here') }}</div>';
            var file_list = '<ul class="file-list"></ul>';
            var rmv = '<div class="remove"><i class="icon-close icons">x</i></div>'

            $fileupload.each(function() {
                var self = this;
                var $dropfield = $('<div class="drop-field"><div class="drop-area"></div></div>');
                $(self).after($dropfield).appendTo($dropfield.find('.drop-area'));
                var $file_list = $(file_list).appendTo($dropfield);
                $dropfield.append(hasitems);
                $dropfield.append(rmv);
                $(noitems).appendTo($file_list);
                var isDropped = false;

                $(self).on("change", function(evt) {
                    if ($(self).val() == "") {
                        $file_list.find('li').remove();
                        $file_list.append(noitems);
                    } else {
                        if (!isDropped) {
                            $dropfield.removeClass('hover');
                            $dropfield.addClass('loaded');
                            var files = $(self).prop("files");
                            traverseFiles(files);
                        }
                    }
                });

                $dropfield.on("dragleave", function(evt) {
                    $dropfield.removeClass('hover');
                    evt.stopPropagation();
                });

                $dropfield.on('click', function(evt) {
                    $(self).val('');
                    $file_list.find('li').remove();
                    $file_list.append(noitems);
                    $dropfield.removeClass('hover').removeClass('loaded');
                });

                $dropfield.on("dragenter", function(evt) {
                    $dropfield.addClass('hover');
                    evt.stopPropagation();
                });

                $dropfield.on("drop", function(evt) {
                    isDropped = true;
                    $dropfield.removeClass('hover');
                    $dropfield.addClass('loaded');
                    var files = evt.originalEvent.dataTransfer.files;
                    traverseFiles(files);
                    isDropped = false;
                });

                function appendFile(file) {
                    $file_list.append('<li>' + file.name + '</li>');
                }

                function traverseFiles(files) {
                    if ($dropfield.hasClass('loaded')) {
                        $file_list.find('li').remove();
                    }

                    if (typeof files !== "undefined") {
                        for (var i = 0, l = files.length; i < l; i++) {
                        appendFile(files[i]);
                        }
                    } else {
                        console.log("No support for the File API in this web browser");
                    }
                }
            });
        };

        $(document).ready(function() {
            globalFunctions.ddInput('input[type="file"]');

            @if (Session::has('done'))
                Swal.fire({
                    icon: 'success',
                    title: "{{ __('message.done') }}",
                    text: '{{ Session::get("done") }}',
                    confirmButtonColor: "var(--bs-primary)",
                    confirmButtonText: "{{ __('message.ok') }}"
                });
            @endif
        });
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header1 d-flex justify-content-between mt-3 ml-3">
                        <div class="header-title">
                            <h4 class="card-title ms-4 mt-1"><b>{{ $pageTitle }}</b>
                                <span class="" data-bs-toggle="tooltip" title="{{ __('message.help_info') }}">
                                    <!-- <svg class="icon-32" width="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M7.67 1.99927H16.34C19.73 1.99927 22 4.37927 22 7.91927V16.0903C22 19.6203 19.73 21.9993 16.34 21.9993H7.67C4.28 21.9993 2 19.6203 2 16.0903V7.91927C2 4.37927 4.28 1.99927 7.67 1.99927ZM11.99 9.06027C11.52 9.06027 11.13 8.66927 11.13 8.19027C11.13 7.70027 11.52 7.31027 12.01 7.31027C12.49 7.31027 12.88 7.70027 12.88 8.19027C12.88 8.66927 12.49 9.06027 11.99 9.06027ZM12.87 15.7803C12.87 16.2603 12.48 16.6503 11.99 16.6503C11.51 16.6503 11.12 16.2603 11.12 15.7803V11.3603C11.12 10.8793 11.51 10.4803 11.99 10.4803C12.48 10.4803 12.87 10.8793 12.87 11.3603V15.7803Z" fill="currentColor"></path>
                                    </svg> -->
                                    <svg class="icon-32" width="25" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M16.334 2.75H7.665C4.644 2.75 2.75 4.889 2.75 7.916V16.084C2.75 19.111 4.635 21.25 7.665 21.25H16.333C19.364 21.25 21.25 19.111 21.25 16.084V7.916C21.25 4.889 19.364 2.75 16.334 2.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M11.9946 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M11.9896 8.2041H11.9996" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </span>
                            </h4> 
                        </div>
                        <div class="card-action mb-2 me-4 mt-1">
                            <a href="javascript:void(0)" class="btn btn-sm mr-3 help"
                                data-modal-form="form" data-size="large"
                                data--href="{{ route('help') }}" data-placement="top"
                                data-app-title="{{ __('message.keyword_value_bulk_upload_fields') }}">
                                {{ __('message.help') }}
                            </a>
                            <a href="javascript:void(0)" class="btn btn-sm mr-3 downloadtemplate"
                                data-modal-form="form" data-size="large"
                                data--href="{{ route('download.template') }}" data-placement="top"
                                data-app-title="{{ __('message.download_template') }}">
                                {{ __('message.download_template') }}
                            </a>                            
                        </div>                        
                    </div>
                    <div class="mb-2 me-4 mt-1" style="text-align: end">
                        <h5 class="mt-2 mr-3"><span class="font-weight-bold">{{ __('message.note') }}</span>
                        {{ __('message.bulk_note') }}
                            </h5>
                    </div>
                    {{ html()->form('POST', route('import.languagewithkeyword'))->attribute('enctype', 'multipart/form-data')->attribute('data-toggle','validator')->open() }} 
                    <div class="card-body">
                        <div class="new-user-info">
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="file" name="language_with_keyword" class="custom-file-input" id="customFile" accept=".csv" required="required">
                                </div>
                            </div>
                            {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end mt-2 mb-2') }}
                        </div>
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
