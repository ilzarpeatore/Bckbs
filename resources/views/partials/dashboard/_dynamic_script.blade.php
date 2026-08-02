<script>
(function($) {
    'use strict';
    
    $(document).ready(function() {
        $('.select2js').select2({
            width: '100%',
        });
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        if($('.datetimepicker').length > 0){
            flatpickr('.datetimepicker', {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
            });
        }

        if($('.datepicker').length > 0){
            flatpickr('.datepicker', {
                enableTime: false,
                dateFormat: 'Y-m-d',
            });
        }

        if($('.maxdatepicker').length > 0){
            flatpickr('.maxdatepicker', {
                minDate: "today",
                enableTime: false,
                dateFormat: 'Y-m-d',
            });
        }

        if($('.timepicker24').length > 0){
            flatpickr('.timepicker24', {
                enableTime: true,
                noCalendar: true,
                time_24hr: true,
                dateFormat: "H:i:S",
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

        function showMessage(message) {
            Swal.fire({
                icon: 'success',
                title: "{{ __('message.done') }}",
                text: message,
                confirmButtonColor: "var(--bs-primary)",
                confirmButtonText: "{{ __('message.ok') }}"
            });
        }

        $(document).on('click', '[data-form="ajax"]', function(f) {
            $('form').validator('update');
            f.preventDefault();
            var current = $(this);
            current.addClass('disabled');
            var form = $(this).closest('form');
            var url = form.attr('action');
            var fd = new FormData(form[0]);

            $.ajax({
                type: "POST",
                url: url,
                data: fd, // serializes form's elements.
                success: function(e) {
                    if (e.status == true) {

                        switch (e.event) {
                            case "submited":
                                showMessage(e.message);
                                $(".modal").modal('hide');
                                $('.dataTable').DataTable().ajax.reload( null, false );
                            break;

                            case "refresh":
                                window.location.reload();
                            break;

                            case "callback":
                                showMessage(e.message);
                                $(".modal").modal('hide');
                                location.reload();
                            break;

                            case "norefresh":
                                showMessage(e.message);
                                $(".modal").modal('hide');
                                getAssignList(e.type);
                            break;

                            default:
                                console.warn("Unhandled event type:", e.event);
                                break;
                        }
                    }
                    if (e.status == false) {
                        if (e.event == 'validation') {
                            errorMessage(e.message);
                        }
                    }
                },
                error: function(error) {

                },
                cache: false,
                contentType: false,
                processData: false,
            });
            f.preventDefault(); // avoid to execute the actual submit of the form.

        });

        $(document).on('change','.change_status', function() {

            var status = $(this).prop('checked') == true ? 1 : 0;
            
            var key_name = $(this).attr('data-name');
            var id = $(this).attr('data-id');
            var type = $(this).attr('data-type');
            $.ajax({
                type: "GET",
                dataType: "json",
                url: "{{ route('changeStatus') }}",
                data: { 'status': status, 'id': id ,'type': type ,[key_name]: key_name },
                success: function(data){
                    if(data.status == false){
                        errorMessage(data.message)
                    }else{
                        showMessage(data.message);
                    }
                }
            });
        })

        $(document).on('click', '[data-toggle="tabajax"]', function(e) {
            e.preventDefault();
            var selectDiv = this;
            ajaxMethodCall(selectDiv);
        });
        
        function ajaxMethodCall(selectDiv) {

            var $this = $(selectDiv),
                loadurl = $this.attr('data-href'),
                targ = $this.attr('data-target'),
                id = selectDiv.id || '';

            $.post(loadurl, function(data) {
                $(targ).html(data);
                $('form').append('<input type="hidden" name="active_tab" value="'+id+'" />');
                if (window.initIntlPhoneInput) {
                    window.initIntlPhoneInput();
                }
            });

            $this.tab('show');
            return false;
        }

        $('form[data-toggle="validator"]').on('submit', function (e) {
            window.setTimeout(function () {
                var errors = $('.has-error')
                if (errors.length) {
                    $('html, body').animate({ scrollTop: "0" }, 500);
                    e.preventDefault()
                }
            }, 0);
        });   
        
        $(document).on('click','[data--confirmation="true"]',function(e){
            e.preventDefault();
            var form = $(this).attr('data--submit');

            var title = $(this).attr('data-title');

            var message = $(this).attr('data-message');

            var ajaxtype = $(this).attr('data--ajax');
            if(form == 'confirm_form') {
                $('#confirm_form').attr('action', $(this).attr('href'));
            }
            let __this = this

            confirmation(form,title,message,ajaxtype,__this);
        });

        function confirmation(form,title = "{{ __('message.confirmation') }}",message = "{{ __('message.delete_msg') }}",ajaxtype=false,_this) 
        {
            const storageDark = localStorage.getItem('theme');
            const theme = (storageDark == "light") ? 'material' : 'dark';
            $.confirm({
                content: message,
                type: '',
                title: title,
                scrollToPreviousElement: false,
                scrollToPreviousElementAnimate: false,
                buttons: {
                    yes: {
                        action: function () { 
                            if(ajaxtype == 'delete') {
                                let url = _this;
                                $.ajax({
                                    url: url,
                                    type: 'DELETE',
                                    success: function(response) {
                                        showMessage(response.message)
                                        if( response.route != null ) {
                                            window.location.href = response.route;
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                    }
                                });    
                                return;
                            }
                            
                            if(ajaxtype == 'true') {
                                let url = _this;

                                let data = $('[data--submit="'+form+'"]').serializeArray();
                                $.post(url, data).then(response => {
                                    if(response.status) {
                                        if(response.event == 'norefresh') {
                                            getAssignList(response.type);
                                        }
                                        if(response.image != null){
                                            $(_this).remove();
                                            $('#'+response.preview).attr('src',response.image)
                                            if (jQuery.inArray(response.preview, ["service_attachment_preview"]) !== -1) {
                                                $('#'+response.preview+"_"+response.id).remove()
                                                let total_file = $('.remove-file').length;
                                                if(total_file == 0){
                                                    $('.service_attachment_div').remove();
                                                }
                                            }
                                            if(response.preview == 'site_logo_preview'){
                                                $('.'+response.preview).attr('src',response.image);
                                            }
                                            if(response.preview == 'site_favicon_preview'){
                                                $('.'+response.preview).attr('href',response.image);
                                            }

                                            if(response.preview == 'site_dark_logo_preview'){
                                                $('.'+response.preview).attr('src',response.image);
                                            }

                                            showMessage(response.message)
                                            return true;
                                        }
                                        if( $('.dataTable').length > 0){
                                            $('.dataTable').DataTable().ajax.reload( null, false );
                                        }
                                        showMessage(response.message)
                                    }
                                    if(response.status == false){
                                        errorMessage(response.message)
                                    }
                                })
                            } else {
                                if (form !== undefined && form){
                                    $(document).find('[data--submit="'+form+'"]').submit();
                                }else{
                                    return true;
                                }
                            }
                        }
                    },
                    no: {
                        action: function () {}
                    },
                },
                theme: theme
            });
            return false;
        }
        $(document).on('change', '.file-input', function() {
            readURL(this);
        })
        $(".file-upload").on('change', function(){
            readURL(this);
        });

        $(".upload-button").on('click', function() {
            $(".file-upload").click();
        });

        $(document).on('change', '.custom-file-input', function() {
            readURL(this);
        })

        function readURL(input) {
            var target = $(input).attr('data--target');
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                var field_name = $(input).attr('name');
                var msg = "{{ __('message.image_png_jpg') }}";
                
                if (jQuery.inArray(field_name, ['exercise_video']) !== -1) {
                    res = isVideoAttachments(input.files[0].name);

                    if(res == false) {
                        var msg = __('message.files_not_allowed');
                        $(input).val("");
                        flag = false;
                    }
                }  else if( jQuery.inArray(field_name, ['language_with_keyword']) !== -1){
                    var res = isCSV(input.files[0].name);
                    msg = "{{ __('message.image_csv') }}";
                    if ($('.selected_file').length > 0) {
                        $('.selected_file').text(input.files[0].name);
                    }
                } else {
                    var res = isImage(input.files[0].name);
                }

                if (res == false) {
                    errorMessage(msg)
                    $(input).val("");
                    return false;
                }
                reader.onload = function(e) {
                    $('.'+target).attr('src', e.target.result);
                };

                reader.readAsDataURL(input.files[0]);
            }

            var modal = $(input).attr('data--modal');

            if (modal !== undefined && modal !== null && modal === 'modal')
                $('.image_upload-modal').modal('hide');

        }

        function isCSV(filename) {
            var ext = getExtension(filename);
            var validExtensions = ['csv'];
 
            if (jQuery.inArray(ext.toLowerCase(), validExtensions) !== -1) {
                return true;
            }
            return false;
        }

        function getExtension(filename) {
            var parts = filename.split('.');
            return parts[parts.length - 1];
        }

        function isImage(filename) {
            var ext = getExtension(filename);
            switch (ext.toLowerCase()) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'svg':
                case 'ico':
                    return true;
            }
            return false;
        }

        function isVideoAttachments(filename) {
            var ext = getExtension(filename);
            var validExtensions = [ 'mp4', 'avi', 'mkv', '3gp', 'wmv', 'mov', 'flv' ];

            if (jQuery.inArray(ext.toLowerCase(), validExtensions) !== -1) {
                return true;
            }
            return false;
        }


        function isDocuments(filename) {
            var ext = getExtension(filename);
            var validExtensions = ['jpg', 'pdf', 'jpeg', 'gif', 'png'];

            if (jQuery.inArray(ext.toLowerCase(), validExtensions) !== -1) {
                return true;
            }
            return false;
        }

        function isAttachments(filename) {
            var ext = getExtension(filename);
            var validExtensions = ['jpg', 'pdf', 'jpeg', 'gif', 'png', 'mp4', 'avi'];
            
            if (jQuery.inArray(ext.toLowerCase(), validExtensions) !== -1) {
                return true;
            }
            return false;
        }
    @if(in_array('phone',$assets ?? []))
        //PHONE 
        function initIntlPhoneInput() {
            var input = document.querySelector("#number");
            if (!input) {
                return;
            }
            if (input.dataset.itiInitialized === '1') {
                return;
            }
            var errorMsg = document.querySelector("#error-msg");
            var validMsg = document.querySelector("#valid-msg");

            var iti = window.intlTelInput(input, {
                hiddenInput: "contact_number",
                separateDialCode: true,
                utilsScript: "{{ asset('vendor/intlTelInput/js/utils.js') }}"
            });
            input.dataset.itiInitialized = '1';

            input.addEventListener("countrychange", function() {
                numblockwidthCount();
                itiPhoneNumberWidthCount();
                validate();
            });

            var errorMap = {!! json_encode([
                __('message.invalid_number'),
                __('message.invalid_country_code'),
                __('message.too_short'),
                __('message.too_long'),
                __('message.local_only'),
            ]) !!};

            const phone = $('#number');
            const err = $('#error-msg');
            const succ = $('#valid-msg');

            var reset = function() {
                err.addClass('d-none');
                succ.addClass('d-none');
                validate();
            };

            // Ensure correct phone number format on blur
            $(document).off('blur.itiPhone keyup.itiPhone', '#number');
            $(document).on('blur.itiPhone keyup.itiPhone', '#number', function() {
                reset();
                var val = $(this).val();
                if (val.match(/[^0-9\.\+.\s.]/g)) {
                    $(this).val(val.replace(/[^0-9\.\+.\s.]/g, ''));
                }
                if (val === '') {
                    $('[type="submit"]').removeClass('disabled').prop('disabled', false);
                }
            });

            input.addEventListener('change', reset);
            input.addEventListener('keyup', reset);

            var errorCode = '';

            function validate() {
                if (input.value.trim()) {
                    if (iti.isValidNumber()) {
                        succ.removeClass('d-none');
                        err.html('');
                        err.addClass('d-none');
                        input.classList.remove('is-invalid');
                        $('[type="submit"]').removeClass('disabled').prop('disabled', false);
                    } else {
                        errorCode = iti.getValidationError();
                        err.html(errorMap[errorCode]);
                        err.removeClass('d-none');
                        input.classList.add('is-invalid');
                        phone.closest('.form-group').addClass('has-danger');
                        $('[type="submit"]').addClass('disabled').prop('disabled', true);
                    }
                }
            }

            // **Update hidden input before form submission**
            $(document).off('submit.itiPhone', 'form');
            $(document).on('submit.itiPhone', 'form', function() {
                var fullNumber = iti.getNumber(); // Get full number with country code
                $('input[name="phone_number"]').val(fullNumber);
            });

            function numblockwidthCount() {
                let is_header = document.querySelector('.iti__flag-container');
                if (is_header !== null) {
                    let numblockwidth = document.querySelector('.iti__flag-container')?.getBoundingClientRect().width;
                    document.querySelector(':root').style.setProperty('--block-width', numblockwidth + 'px');
                }
            }

            function itiPhoneNumberWidthCount() {
                let is_number = document.querySelector('#number');
                if (is_number !== null) {
                    let phoneNumberwidth = document.querySelector('#number')?.getBoundingClientRect().width;
                    document.querySelector(':root').style.setProperty('--phone-num-block-width', phoneNumberwidth + 'px');
                }
            }
            itiPhoneNumberWidthCount();
            numblockwidthCount();

            jQuery(window).off('resize.itiPhone');
            jQuery(window).on('resize.itiPhone', function () {
                numblockwidthCount();
                itiPhoneNumberWidthCount();
            });
        }

        window.initIntlPhoneInput = initIntlPhoneInput;
        initIntlPhoneInput();
    @endif

    });
})(jQuery);
</script>