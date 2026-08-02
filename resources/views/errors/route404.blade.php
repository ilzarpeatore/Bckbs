<x-guest-layout>
    <div class="container">
        <div class="row no-gutters height-self-center">
            <div class="col-sm-12 text-center align-self-center">
                <div class="mm-error position-relative">
                     <img src="{{ asset('images/error/404-page.png') }}" class="img-fluid mm-error-img mx-auto" alt="404">
                    <img src="{{ asset('images/error/404-page-dark.png') }}" class="img-fluid mm-error-img mm-error-img-dark mx-auto" alt="404">
                    <h2 class="mb-0 mt-4">{{ __('message.error_404_title') }}</h2>
                    <p>{{ __('message.error_404_description') }}</p>
                    <a class="btn btn-primary d-inline-flex align-items-center mt-3" href="{{ route('dashboard') }}">{{ __('message.back_to_home') }}</a>
                </div>
            </div>
        </div>
   </div>
</x-guest-layout>