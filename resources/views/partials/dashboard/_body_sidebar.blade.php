<aside class="sidebar sidebar-default navs-rounded-all">
    <div class="sidebar-header d-flex align-items-center justify-content-start">
        <a href="{{route('dashboard')}}" class="navbar-brand">
            <div class="logo-main">
                <img class="logo-normal img-fluid site_logo_preview" src="{{ getSingleMedia(appSettingData('get'),'site_logo',null) }}" height="30" alt="site_logo">
                <img class="logo-normal dark-normal site_dark_logo_preview img-fluid" src="{{ getSingleMedia(appSettingData('get'),'site_dark_logo',null) }}" height="30" alt="site_dark_logo">
                <img class="logo-mini img-fluid site_mini_logo_preview" src="{{ getSingleMedia(appSettingData('get'), 'site_mini_logo',null) }}" height="30" alt="mini_logo">
                <img class="logo-mini dark-mini site_dark_mini_logo_preview img-fluid" src="{{ getSingleMedia(appSettingData('get'), 'site_dark_mini_logo',null) }}" height="30" alt="dark_mini_logo">
            </div>
        </a>
        <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
            <i class="icon">
                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.25 12.2744L19.25 12.2744" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M10.2998 18.2988L4.2498 12.2748L10.2998 6.24976" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </i>
        </div>
    </div>
    <div class="sidebar-body pt-0 data-scrollbar pb-5">
        <div class="sidebar-list" id="sidebar">
        @include('partials.dashboard.vertical-nav') 
        </div>
    </div>
    <div class="sidebar-footer"></div>
</aside>