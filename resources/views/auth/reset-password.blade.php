<x-guest-layout>
    <section class="login-content">
        <div class="row m-0 align-items-center bg-white vh-100">
            <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
                <img src="{{ asset('images/auth/02.png') }}" class="img-fluid gradient-main animated-scaleX" alt="images">
            </div>
            <div class="col-md-6 p-0">
                <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
                    <div class="card-body">
                        <div class="auth-logo">
                            <a href="{{route('dashboard')}}" class="navbar-brand d-flex align-items-center">
                                <img src="{{ getSingleMedia(appSettingData('get'),'site_logo',null) }}" class="img-fluid mode light-img rounded-normal light-logo site_logo_preview" alt="logo">             
                                <img src="{{ getSingleMedia(appSettingData('get'),'site_dark_logo',null) }}" class="img-fluid mode dark-img rounded-normal darkmode-logo site_dark_logo_preview" alt="dark-logo">
                            </a>
                        </div>
                        <h4 class="mb-2 text-right">{{ __('auth.reset_password') }}</h4>
                        <!-- Validation Errors -->
                        <x-auth-validation-errors class="mb-4" :errors="$errors" />

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf

                            <!-- Password Reset Token -->
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">

                            <!-- Email Address -->
                            <div class="form-group">
                                {{ html()->label(__('message.email') . ' <span class="text-danger">*</span>', 'email')->class('form-control-label') }}
                                {{ html()->email('email', old('email') ?? $request->email)->placeholder(__('message.email'))->class('form-control')->required()->autofocus() }}
                            </div>

                            <!-- Password -->
                            <div class="form-group">
                                {{ html()->label(__('message.password') . ' <span class="text-danger">*</span>', 'password')->class('form-control-label') }}
                                {{ html()->password('password')->class('form-control')->placeholder(__('message.password'))->required() }}
                            </div>

                            <!-- Confirm Password -->
                            <div class="form-group">
                                {{ html()->label(__('auth.password_confirmation') . ' <span class="text-danger">*</span>', 'password_confirmation')->class('form-control-label') }}
                                {{ html()->password('password_confirmation')->class('form-control')->placeholder(__('auth.password_confirmation'))->required() }}
                            </div>

                            <button class="btn btn-primary">
                                {{ __('auth.reset_password') }}
                            </button>
                        </form>
                    </div>
                </div>
                <div class="sign-bg sign-bg-right">
                    <svg width="280" height="230" viewBox="0 0 431 398" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g opacity="0.05">
                            <rect x="-157.085" y="193.773" width="543" height="77.5714" rx="38.7857"
                                transform="rotate(-45 -157.085 193.773)" fill="#3B8AFF" />
                            <rect x="7.46875" y="358.327" width="543" height="77.5714" rx="38.7857"
                                transform="rotate(-45 7.46875 358.327)" fill="#3B8AFF" />
                            <rect x="61.9355" y="138.545" width="310.286" height="77.5714" rx="38.7857"
                                transform="rotate(45 61.9355 138.545)" fill="#3B8AFF" />
                            <rect x="62.3154" y="-190.173" width="543" height="77.5714" rx="38.7857"
                                transform="rotate(45 62.3154 -190.173)" fill="#3B8AFF" />
                        </g>
                    </svg>
                </div>
            </div>
        </div>
    </section>
</x-guest-layout>
