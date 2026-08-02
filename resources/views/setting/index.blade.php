@push('scripts')
    <script>
        $(document).ready(function(event)
        {
            var $this = $('.tabslink .nav-item').find('a.active');
            loadurl = "{{route('layout_page')}}?page={{$page}}";

            targ = $this.attr('data-target');

            id = this.id || '';

            $.post(loadurl,{ '_token': $('meta[name=csrf-token]').attr('content') } ,function(data) {
                $(targ).html(data);
                if (window.initIntlPhoneInput) {
                    window.initIntlPhoneInput();
                }
            });

            $this.tab('show');
            return false;
        });
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h5 class="font-weight-bold">{{ $pageTitle ?? __('message.list') }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-lg-12">
                                <ul class="nav flex-column nav-pills me-3 tabslink" id="tabs-text" role="tablist">
                                    @if(in_array( $page, ['profile-form','password-form']))
                                        <li class="nav-item">
                                            <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=profile-form" data-target=".paste_here" class="nav-link {{ $page == 'profile-form' ? 'active':'' }}" data-toggle="tabajax" rel="tooltip"> {{ __('message.profile')}} </a>
                                        </li>
                                        <li class="nav-item">
                                            <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=password-form" data-target=".paste_here" class="nav-link {{ $page == 'password-form' ? 'active':'' }}" data-toggle="tabajax" rel="tooltip"> {{ __('message.change_password') }} </a>
                                        </li>
                                    @else
                                        @hasanyrole('admin')
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=general-setting" data-target=".paste_here" class="nav-link {{ $page == 'general-setting' ? 'active' : '' }}" data-toggle="tabajax" rel="tooltip"> {{ __('message.general_settings') }}</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=mobile-config" data-target=".paste_here" class="nav-link {{$page=='mobile-config'?'active':''}}"  data-toggle="tabajax" rel="tooltip"> {{ __('message.mobile_config') }}</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=mail-setting" data-target=".paste_here" class="nav-link {{ $page == 'mail-setting' ?' active' : '' }}" data-toggle="tabajax" rel="tooltip"> {{ __('message.mail_settings') }}</a>
                                            </li>
                                            @if (Module::has('Frontend') && Module::isEnabled('Frontend'))
                                                <li class="nav-item">
                                                    <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=firebase-setting" data-target=".paste_here" class="nav-link {{ $page == 'firebase-setting' ?' active' : '' }}" data-toggle="tabajax" rel="tooltip"> {{ __('frontend::message.firebase_settings') }}</a>
                                                </li>
                                            @endif
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=language-setting" data-target=".paste_here" class="nav-link {{ $page == 'language-setting' ? 'active': '' }}"  data-toggle="tabajax" rel="tooltip"> {{ __('message.language_settings') }}</a>
                                            </li>
                                            @if (Module::has('Frontend') && Module::isEnabled('Frontend'))
                                                <li class="nav-item">
                                                    <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=frontend-language-setting" data-target=".paste_here" class="nav-link {{ $page == 'frontend-language-setting' ? 'active': '' }}"  data-toggle="tabajax" rel="tooltip"> {{ __('frontend::message.frontend_language_settings') }}</a>
                                                </li>
                                            @endif
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=payment-setting" data-target=".paste_here" class="nav-link {{ $page == 'payment-setting' ? 'active': '' }}"  data-toggle="tabajax" rel="tooltip"> {{ __('message.payment_settings') }}</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=subscription-setting" data-target=".paste_here" class="nav-link {{ $page == 'subscription-setting' ? 'active': '' }}"  data-toggle="tabajax" rel="tooltip"> {{ __('message.subscription_setting') }}</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=mail-alert-setting" data-target=".paste_here" class="nav-link {{ $page == 'mail-alert-setting' ?' active' : '' }}" data-toggle="tabajax" rel="tooltip"> {{ __('message.mail_alert_setting') }}</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="javascript:void(0)" data-href="{{ route('layout_page') }}?page=login-enable-setting" data-target=".paste_here" class="nav-link {{ $page == 'login-enable-setting' ? 'active': '' }}"  data-toggle="tabajax" rel="tooltip"> {{ __('message.login_enable_setting') }}</a>
                                            </li>                                            
                                        @endhasanyrole
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-lg-12">
                                <div class="tab-content" id="pills-tabContent-1">
                                    <div class="tab-pane active p-1" >
                                        <div class="paste_here"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
