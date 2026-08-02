@push('scripts')
    <script>
        $(document).ready(function() {
            $('.selectAll').click(function(){
                var usertype = $(this).attr('data-usertype');
                
                if($(this).is(':checked') ) {
                    $('#'+usertype+'_list').find('option').prop('selected', true);
                    $('#'+usertype+'_list').trigger('change');
                } else {
                    $('#'+usertype+'_list').find('option').prop('selected', false);
                    $('#'+usertype+'_list').trigger('change');
                }
            });
        }); 
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'POST', route('pushnotification.store',['notify_type' => 'resend']) )->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('pushnotification.store'))->attribute('enctype', 'multipart/form-data')->open() }} 
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('pushnotification.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                {{ html()->label(__('message.user') . ' <span class="text-danger">*</span>', 'user')->class('form-control-label') }}
                                {{ html()->select('user[]', $user, old('user'))->class('select2js form-control')->attribute('id', 'user_list')->attribute('multiple', 'multiple')->attribute('required', 'required') }}
                            </div>

                            <div class="form-group col-md-2">
                                <div class="custom-control custom-checkbox mt-4 pt-3">
                                    <input type="checkbox" class="custom-control-input selectAll" id="all_user" data-usertype="user">
                                    <label class="custom-control-label" for="all_user">{{ __('message.selectall') }}</label>
                                </div>
                            </div>

                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required','required') }}
                            </div>

                            <div class="form-group col-md-12">
                                {{ html()->label(__('message.message') . ' <span class="text-danger">*</span>', 'message')->class('form-control-label') }}
                                {{ html()->textarea('message')->class('form-control textarea')->rows(3)->attribute('required', 'required')->placeholder(__('message.message')) }}                                
                            </div>

                            <div class="form-group col-md-4">
                                <label class="form-control-label" for="notification_image">{{ __('message.image') }} </label>
                                <div class="">
                                    <input class="form-control file-input" type="file" name="notification_image" accept="image/*" data--target='notification_image_preview'>
                                </div>
                            </div>
                            <div class="col-md-2 mb-2">
                                 <img id="notification_image_preview" src="{{ asset('images/default.png') }}" alt="image" class="attachment-image mt-1 notification_image_preview"> 
                            </div>
                        </div>
                        <hr>
                        {{ html()->submit( __('message.send') )->class('btn btn-md btn-primary float-end') }}
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
