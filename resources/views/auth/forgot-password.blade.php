<x-guest-layout>
   <section class="login-content">
      <div class="row m-0 align-items-center bg-white vh-100">
         <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
            <img src="{{asset('images/auth/02.png')}}" class="img-fluid gradient-main animated-scaleX" alt="images">
         </div>
         <div class="col-md-6 p-0">               
            <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
               <div class="card-body">
                     <div class="auth-logo ">
                        <a href="{{route('dashboard')}}" class="navbar-brand d-flex align-items-center">
                           <img src="{{ getSingleMedia(appSettingData('get'),'site_logo',null) }}" class="img-fluid mode light-img rounded-normal light-logo site_logo_preview" alt="logo">                             
                           <img src="{{ getSingleMedia(appSettingData('get'),'site_dark_logo',null) }}" class="img-fluid mode dark-img rounded-normal darkmode-logo site_dark_logo_preview" alt="dark-logo">                              
                        </a>
                     </div>
                  <h4 class="mb-2 text-right">{{ __('auth.reset_password') }}</h4>
                  <p>{{ __('auth.reset_password_description') }}</p>
                  
                  <x-auth-session-status class="mb-4" :status="session('status')" />

                  <x-auth-validation-errors class="mb-4" :errors="$errors" />
                  <form method="POST" action="{{ route('password.email') }}">
                     {{csrf_field()}}
                     <div class="row">
                        <div class="col-lg-12">
                           <div class="floating-label form-group">
                              <label for="email" class="form-label">{{ __('message.email') }}</label>
                              <input type="email" name="email" class="form-control" id="email" aria-describedby="email" placeholder=" ">
                           </div>
                        </div>
                     </div>
                     <button type="submit" class="btn btn-primary btn-block col-lg-12">{{ __('auth.email_password_reset_link') }}</button>
                  </form>
               </div>
            </div>
            <div class="sign-bg sign-bg-right">
               
            </div>
         </div>
      </div>
   </section>
</x-guest-layout>
