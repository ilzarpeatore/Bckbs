{{ html()->form('POST', route('settingsUpdates'))->attribute('enctype', 'multipart/form-data')->attribute('data-toggle', 'validator')->open() }}
{{ html()->hidden('id',$settings->id ?? null) }}
{{ html()->hidden('page', $page)->class('form-control') }}
<div class="row">
    <div class="col-lg-6"> 
        <div class="form-group">
            <label for="avatar" class="col-sm-3 form-control-label">{{ __('message.logo') }}</label>
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-4 position-relative">
                        <img src="{{ getSingleMedia($settings,'site_logo') }}" width="100"  id="site_logo_preview" alt="site_logo" class="image site_logo site_logo_preview">
                        @if(getMediaFileExit($settings, 'site_logo'))
                            <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $settings->id, 'type' => 'site_logo']) }}"
                                data--submit="confirm_form"
                                data--confirmation='true'
                                data--ajax="true"
                                title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-message='{{ __("message.remove_file_msg") }}'>
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                    <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                    <div class="col-sm-8">
                        <div class="col-md-12">
                            <input class="form-control file-input" type="file" name="site_logo" accept="image/*" id="site_logo" data--target="site_logo_preview" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="avatar" class="col-sm-3 form-control-label">{{ __('message.dark_logo') }}</label>
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-4 position-relative">
                        <img src="{{ getSingleMedia($settings,'site_dark_logo') }}" width="100"  id="site_dark_logo_preview" alt="site_dark_logo" class="image site_dark_logo site_dark_logo_preview border">
                        @if(getMediaFileExit($settings, 'site_dark_logo'))
                            <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $settings->id, 'type' => 'site_dark_logo']) }}"
                                data--submit="confirm_form"
                                data--confirmation='true'
                                data--ajax="true"
                                title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-message='{{ __("message.remove_file_msg") }}'>
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                    <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                    <div class="col-sm-8">
                        <div class="col-md-12">
                            <input class="form-control file-input" type="file" name="site_dark_logo" accept="image/*" id="site_dark_logo"  data--target="site_dark_logo_preview" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="avatar" class="col-sm-3 form-control-label">{{ __('message.mini_logo') }}</label>
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-4 position-relative">
                        <img src="{{ getSingleMedia($settings,'site_mini_logo') }}" width="100"  id="site_mini_logo_preview" alt="site_mini_logo" class="image site_mini_logo site_mini_logo_preview">
                        @if(getMediaFileExit($settings, 'site_mini_logo'))
                            <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $settings->id, 'type' => 'site_mini_logo']) }}"
                                data--submit='confirm_form' data--confirmation='true' data--ajax='true'
                                title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-message='{{ __("message.remove_file_msg") }}'>
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                    <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                    <div class="col-sm-8">
                        <div class="col-md-12">
                            <input class="form-control file-input" type="file" name="site_mini_logo" accept="image/*" id="site_mini_logo" data--target="site_mini_logo_preview" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="avatar" class="col-sm-3 form-control-label">{{ __('message.dark_mini_logo') }}</label>
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-4 position-relative">
                        <img src="{{ getSingleMedia($settings,'site_dark_mini_logo') }}" width="100"  id="site_dark_mini_logo_preview" alt="site_dark_mini_logo" class="image site_dark_mini_logo site_dark_mini_logo_preview border">
                        @if(getMediaFileExit($settings, 'site_dark_mini_logo'))
                            <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $settings->id, 'type' => 'site_dark_mini_logo']) }}"
                                data--submit="confirm_form" data--confirmation='true' data--ajax="true"
                                title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-message='{{ __("message.remove_file_msg") }}'>
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                    <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                    <div class="col-sm-8">
                        <div class="col-md-12">
                            <input class="form-control file-input" type="file" name="site_dark_mini_logo" accept="image/*" id="site_dark_mini_logo" data--target="site_dark_mini_logo_preview" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="avatar" class="col-sm-6 form-control-label">{{ __('message.favicon') }}</label>
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-sm-4 position-relative">
                        <img src="{{ getSingleMedia($settings,'site_favicon') }}" height="30"  id="site_favicon_preview" alt="site_favicon" class="image site_favicon site_favicon_preview">
                        @if(getMediaFileExit($settings, 'site_favicon'))
                            <a class="text-danger remove-file" href="{{ route('remove.file', ['id' => $settings->id, 'type' => 'site_favicon']) }}"
                                data--submit="confirm_form"
                                data--confirmation='true'
                                data--ajax="true"
                                title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-title='{{ __("message.remove_file_title" , ["name" =>  __("message.image") ]) }}'
                                data-message='{{ __("message.remove_file_msg") }}'>
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path opacity="0.4" d="M16.34 1.99976H7.67C4.28 1.99976 2 4.37976 2 7.91976V16.0898C2 19.6198 4.28 21.9998 7.67 21.9998H16.34C19.73 21.9998 22 19.6198 22 16.0898V7.91976C22 4.37976 19.73 1.99976 16.34 1.99976Z" fill="currentColor"></path>
                                    <path d="M15.0158 13.7703L13.2368 11.9923L15.0148 10.2143C15.3568 9.87326 15.3568 9.31826 15.0148 8.97726C14.6728 8.63326 14.1198 8.63426 13.7778 8.97626L11.9988 10.7543L10.2198 8.97426C9.87782 8.63226 9.32382 8.63426 8.98182 8.97426C8.64082 9.31626 8.64082 9.87126 8.98182 10.2123L10.7618 11.9923L8.98582 13.7673C8.64382 14.1093 8.64382 14.6643 8.98582 15.0043C9.15682 15.1763 9.37982 15.2613 9.60382 15.2613C9.82882 15.2613 10.0518 15.1763 10.2228 15.0053L11.9988 13.2293L13.7788 15.0083C13.9498 15.1793 14.1728 15.2643 14.3968 15.2643C14.6208 15.2643 14.8448 15.1783 15.0158 15.0083C15.3578 14.6663 15.3578 14.1123 15.0158 13.7703Z" fill="currentColor"></path>
                                </svg>
                            </a>
                        @endif
                    </div>
                    <div class="col-sm-8">
                        <div class="col-md-12">
                            <input class="form-control file-input" type="file" name="site_favicon" accept="image/*" id="site_favicon" data--target="site_favicon_preview" />
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="form-group">
            {{ html()->label(__('message.site_name'))->class('col-sm-6 form-control-label')->for('site_name') }}
            <div class="col-sm-12">
                {{ html()->text('site_name')->value($settings->site_name ?? '')->class('form-control')->placeholder(__('message.site_name')) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.site_description'))->class('col-sm-6 form-control-label')->for('site_description') }}
            <div class="col-sm-12">
                {{ html()->textarea('site_description')->value($settings->site_description ?? '')->class('form-control textarea')->rows(3)->placeholder(__('message.site_description')) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.contact_email'))->class('col-sm-6 form-control-label')->for('contact_email') }}
            <div class="col-sm-12">
                {{ html()->text('contact_email')->value($settings->contact_email ?? '')->class('form-control')->placeholder(__('message.contact_email')) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.contact_number'))->class('col-sm-6 form-control-label')->for('contact_number') }}
            <div class="col-sm-12">
                {{ html()->text('contact_number')->value($settings->contact_number ?? '')->class('form-control')->placeholder(__('message.contact_number')) }}
            </div>
        </div>        
    </div>
    <div class="col-lg-6">
        <div class="form-group">
            {{ html()->label(__('message.default_language'))->class('col-sm-12 form-control-label')->for('default_language') }}
            <div class="col-sm-12">
                <select class="form-control select2js default_language" name="env[DEFAULT_LANGUAGE]" id="default_language">
                    @foreach(languagesArray() as $language)
                        <option value="{{ $language['id'] }}" {{ config('app.locale') == $language['id']  ? 'selected' : '' }}  >{{ $language['title'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group">
            {{ html()->label(__('message.language_option'))->class('col-sm-12 form-control-label')->for('language_option') }}
            <div class="col-sm-12">
                <select class="form-control select2js language_option" name="language_option[]" id="language_option" multiple>
                    @foreach(languagesArray() as $language)
                        @if(config('app.locale') == $language['id']  )
                            <option value="{{ $language['id'] }}"  disabled="">{{ $language['title'] }}</option>
                        @else
                            <option value="{{ $language['id'] }}" {{in_array($language['id'], $settings->language_option) ? 'selected' : '' }}  >{{ $language['title'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>

        @if (Module::has('Frontend') && Module::isEnabled('Frontend')) 
            <div class="form-group col-md-12">
                {{ html()->label(__('frontend::message.theme_color'))->class('col-sm-6 form-control-label')->for('theme_color') }}
                <div class="input-group">
                    {{ html()->input('color', 'bg_color', isset($settings) ? $settings->color : '#ec7e4a')->class('form-control form-control-color')->attribute('required')->id('bg_color_picker') }}   
                    {{ html()->text('color')->value( isset($settings) ? $settings->color : '#ec7e4a',)->class('form-control')->attribute('required')->id('bg_color_code') }}
                </div>
            </div>
        @endif

        <div class="form-group">
            {{ html()->label(__('message.timezone'))->class('col-sm-6 form-control-label')->for('timezone') }}
            <div class="col-sm-12">
                {{ html()->select('timezone', [auth()->user()->timezone => timeZoneList()[auth()->user()->timezone]], old('timezone'))->class('form-control select2js')->attribute('required')->attribute('data-ajax--url', route('ajax-list', ['type' => 'timezone']))
                    ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.timezone')])) }}
            </div>
        </div>

        <div class="form-group">
            {{ html()->label(__('message.facebook_url'))->class('col-sm-6 form-control-label')->for('facebook_url') }}
            <div class="col-sm-12">
                {{ html()->text('facebook_url')->value($settings->facebook_url ?? '')->class('form-control')->placeholder(__('message.enter_name', ['name' => __('message.facebook_url')])) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.twitter_url'))->class('col-sm-6 form-control-label')->for('twitter_url') }}
            <div class="col-sm-12">
                {{ html()->text('twitter_url')->value($settings->twitter_url ?? '')->class('form-control')->placeholder(__('message.enter_name', ['name' => __('message.twitter_url')])) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.linkedin_url'))->class('col-sm-6 form-control-label')->for('linkedin_url') }}
            <div class="col-sm-12">
                {{ html()->text('linkedin_url')->value($settings->linkedin_url ?? '')->class('form-control')->placeholder(__('message.enter_name', ['name' => __('message.linkedin_url')])) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.instagram_url'))->class('col-sm-6 form-control-label')->for('instagram_url') }}
            <div class="col-sm-12">
                {{ html()->text('instagram_url')->value($settings->instagram_url ?? '')->class('form-control')->placeholder(__('message.enter_name', ['name' => __('message.instagram_url')])) }}
            </div>
        </div>
        
        <div class="form-group">
            {{ html()->label(__('message.copyright_text'))->class('col-sm-6 form-control-label')->for('site_copyright') }}
            <div class="col-sm-12">
                {{ html()->text('site_copyright')->value($settings->site_copyright ?? '')->class('form-control')->placeholder(__('message.copyright_text')) }}
            </div>
        </div>
        <div class="form-group">
            {{ html()->label(__('message.help_support_url'))->class('col-sm-6 form-control-label')->for('help_support_url') }}
            <div class="col-sm-12">
                {{ html()->text('help_support_url')->value($settings->help_support_url ?? '')->class('form-control')->placeholder(__('message.enter_name', ['name' => __('message.help_support_url')])) }}
            </div>
        </div>        
    </div>
    <hr>
    <div class="col-lg-12"> 
        <div class="form-group">
            <div class="col-md-offset-3 col-sm-12 ">
                {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-md-end') }}
            </div>
        </div>
    </div>
</div>
@if(isset($id))
    {{ html()->closeModelForm() }}
@else
    {{ html()->form()->close() }}
@endif
<script>
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
            case 'ico':
                return true;
        }
        return false;
    }
    function readURL(input) {
        var target = $(input).attr('data--target');
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            var res = isImage(input.files[0].name);
            if(res == false){
                var msg = "{{ __('message.image_png_jpg') }}";
                
                Swal.fire({
                    icon: 'error',
                    title: "{{ __('message.opps') }}",
                    text: msg,
                    confirmButtonColor: "var(--bs-primary)",
                    confirmButtonText: "{{ __('message.ok') }}"
                });
                $(input).val("");
                return false;
            }
            reader.onload = function(e){
                console.log(target);
                $('.'+target).attr('src', e.target.result);                
            }

            reader.readAsDataURL(input.files[0]);
        }
    }

    $(document).ready(function (){
        $('.select2js').select2();
       
        $(".file-input").on('change', function(){
            readURL(this);
        });

        $('.default_language').on('change', function (e) {
            var id= $(this).val();
            $('.language_option option:disabled').prop('selected',true);
            $('.language_option option').prop('disabled',false);

            $('.language_option option').each(function(index, val){
                var $this = $(this);
                if(id == $this.val()){
                    $this.prop('disabled',true);
                    $this.prop('selected',false);
                }
            });
            $('.language_option').select2("destroy").select2();
        });
        
        if($('#bg_color_code').length > 0) {
        function colorCodeInput() {
            var colorCode = $('#bg_color_code').val();
            if (colorCode[0] !== '#') {
                colorCode = '#' + colorCode;
            }
            $('#bg_color_code').val(colorCode);
        }

        $('#bg_color_code').on('input', function() {
            colorCodeInput();
            var colorCode = $(this).val();
            $('#bg_color_picker').val(colorCode);
        });

        $('#bg_color_picker').on('input', function() {
            var selectedColor = $(this).val();
            $('#bg_color_code').val(selectedColor);
        });

        colorCodeInput();
        }
    })
</script>
