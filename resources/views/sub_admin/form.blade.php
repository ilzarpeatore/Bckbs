<x-app-layout :assets="$assets ?? []">
    <div>
        <?php
            $id = $id ?? null;
        ?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('subadmin.update', $id) )->attribute('enctype', 'multipart/form-data')->open() }}
        @else
            {{ html()->form('POST', route('subadmin.store'))->attribute('enctype', 'multipart/form-data')->open() }} 
        @endif
        <div class="row">
            <div class="col-xl-3 col-lg-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <div class="profile-img-edit position-relative">
                                <img src="{{ $profileImage ?? asset('images/avatars/01.png')}}" id="profile_image_preview" alt="User-Profile" class="profile_image_preview profile-pic rounded avatar-100">
                                <div class="upload-icone bg-primary">
                                    <svg class="upload-button" width="14" height="14" viewBox="0 0 24 24">
                                        <path fill="#ffffff" d="M14.06,9L15,9.94L5.92,19H5V18.08L14.06,9M17.66,3C17.41,3 17.15,3.1 16.96,3.29L15.13,5.12L18.88,8.87L20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18.17,3.09 17.92,3 17.66,3M14.06,6.19L3,17.25V21H6.75L17.81,9.94L14.06,6.19Z" />
                                    </svg>
                                    <input class="file-upload" type="file" accept="image/*" name="profile_image" data--target="profile_image_preview">
                                </div>
                            </div>
                            
                            <div class="img-extension mt-3">
                                <div class="d-inline-block align-items-center">
                                    <span>{{ __('message.only') }}</span>

                                    @foreach(config('constant.IMAGE_EXTENTIONS') as $extention)
                                        <a href="javascript:void();">.{{  __('message.'.$extention) }}</a>
                                    @endforeach
                                    <span>{{ __('message.allowed') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('message.status') }}</label>
                            <div class="grid" style="--bs-gap: 1rem">
                                <div class="form-check g-col-6">
                                    {{ html()->radio('status', old('status', 'active') == 'active', 'active')->class('form-check-input')->id('status-active') }}
                                    {{ html()->label(__('message.active'))->class('form-check-label')->for('status-active') }}
                                </div>
                                <div class="form-check g-col-6">
                                    {{ html()->radio('status', old('status', 'active') == 'inactive', 'inactive')->class('form-check-input')->id('status-inactive') }}
                                    {{ html()->label(__('message.inactive'))->class('form-check-label')->for('status-inactive') }}
                                </div>
                                <div class="form-check g-col-6">
                                    {{ html()->radio('status', old('status', 'active') == 'pending', 'pending')->class('form-check-input')->id('status-pending') }}
                                    {{ html()->label(__('message.pending'))->class('form-check-label')->for('status-pending') }}
                                </div>
                                <div class="form-check g-col-6">
                                    {{ html()->radio('status', old('status', 'active') == 'banned', 'banned')->class('form-check-input')->id('status-banned') }}
                                    {{ html()->label(__('message.banned'))->class('form-check-label')->for('status-banned') }}
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            {{ html()->label(__('message.role').' <span class="text-danger">*</span>') ->class('form-control-label') }}
                            {{ html()->select('user_type', $roles, old('user_type'))
                                ->class('select2js form-group role')
                                ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.role')]))
                                ->attribute('required', 'required') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9 col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }} {{ __('message.information') }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('subadmin.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="new-user-info">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.first_name') . ' <span class="text-danger">*</span>', 'first_name')->class('form-control-label') }}
                                    {{ html()->text('first_name')->placeholder(__('message.first_name'))->class('form-control')->attribute('required','required') }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.last_name') . ' <span class="text-danger">*</span>', 'last_name')->class('form-control-label') }}
                                    {{ html()->text('last_name')->placeholder(__('message.last_name'))->class('form-control')->attribute('required','required') }}
                                </div>
                                
                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.email') . ' <span class="text-danger">*</span>', 'email')->class('form-control-label') }}
                                    {{ html()->email('email')->placeholder(__('message.email'))->class('form-control')->attribute('required','required') }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.username') . ' <span class="text-danger">*</span>', 'username')->class('form-control-label') }}
                                    {{ html()->text('username')->placeholder(__('message.username'))->class('form-control')->attribute('required','required') }}
                                </div>

                                @if(!isset($id))
                                    <div class="form-group col-md-6">
                                        {{ html()->label(__('message.password') . ' <span class="text-danger">*</span>', 'password')->class('form-control-label') }}
                                        {{ html()->password('password')->placeholder(__('message.password'))->class('form-control')->attribute('required','required') }}
                                    </div>
                                @endif

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.phone_number'))->class('form-control-label d-flex') }}
                                    {{ html()->text('phone_number')->placeholder(__('message.phone_number'))->class('form-control')->id('number')->attribute('required','required') }}
                                    <small id="error-msg" class="text-danger d-none"></small>
    					            <small id="valid-msg" class="text-success d-none"></small>
                                </div>

                                <div class="form-group col-md-6">
                                    {{ html()->label(__('message.gender') . ' <span class="text-danger">*</span>', 'gender')->class('form-control-label') }}
                                    {{ html()->select('gender',[ 'male' => __('message.male'), 'female' => __('message.female'),'other' => __('message.other') ], old('gender'))->class('form-control select2js')->attribute('required', 'required') }}
                                </div>
                            </div>
                            <hr>
                            {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
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
