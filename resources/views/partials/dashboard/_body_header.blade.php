@push('scripts')
  <script>
      $(document).ready(function() {
          const savedTheme = localStorage.getItem('theme');
          if (savedTheme) {
              if (savedTheme === 'dark') {
                  $('body').addClass('dark');
                  $('.sit_darkcolor_theam').hide();
                  $('.sit_lightcolor_theam').show();
              } else if(savedTheme === 'light'){
                  $('body').removeClass('dark');
                  $('.sit_darkcolor_theam').show();
                  $('.sit_lightcolor_theam').hide();
              }
          }
          $(".sit_color_theam").click(function() {
              let selectedMode = $(this).data("value");
              if (selectedMode === "dark") {
                  $('body').addClass('dark');
                  $('.sit_darkcolor_theam').hide();
                  $('.sit_lightcolor_theam').show();
                  localStorage.setItem('theme', 'dark');
              } else if(selectedMode === 'light') {
                  $('body').removeClass('dark');
                  $('.sit_darkcolor_theam').show();
                  $('.sit_lightcolor_theam').hide();
                  localStorage.setItem('theme', 'light');
              }
          });
      });
  </script>
@endpush
<nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
  <div class="container-fluid navbar-inner">
       <a href="{{route('dashboard')}}" class="navbar-brand">
            <div class="logo-main">
                <div class="logo-normal">
					          <img class="site_logo_preview" src="{{ getSingleMedia(appSettingData('get'), 'site_logo',null) }}" height="30" alt="site_logo">
                </div>
                <div class="logo-normal dark-normal">
					          <img class="site_dark_logo_preview" src="{{ getSingleMedia(appSettingData('get'), 'site_dark_logo',null) }}" height="30" alt="site_dark_logo">
                </div>
            </div>
      </a>
    <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
      <i class="icon">
        <svg width="20px" height="20px" viewBox="0 0 24 24">
          <path fill="currentColor" d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z" />
      </svg>
      </i>
    </div>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <!-- <span class="navbar-toggler-icon"></span> -->
      <span class="navbar-toggler-icon">
        <span class="navbar-toggler-bar bar1 mt-2"></span>
        <span class="navbar-toggler-bar bar2"></span>
        <span class="navbar-toggler-bar bar3"></span>
      </span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav ms-auto  navbar-list mb-2 mb-lg-0">
        <li class="nav-item theme-scheme-dropdown dropdown iq-dropdown">
            <div class="btn sit_color_theam sit_darkcolor_theam" data-bs-toggle="tooltip" title="{{ __('message.sit_dark_color_theam') }}" data-setting="color-mode" data-name="color" data-value="dark">
              <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor" d="M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8M12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18M20,8.69V4H15.31L12,0.69L8.69,4H4V8.69L0.69,12L4,15.31V20H8.69L12,23.31L15.31,20H20V15.31L23.31,12L20,8.69Z" />
              </svg>
            </div>
            <div class="btn active sit_color_theam sit_lightcolor_theam" data-bs-toggle="tooltip" title="{{ __('message.sit_light_color_theam') }}" data-setting="color-mode" data-name="color" data-value="light" style="display:none;">
              <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill="currentColor" d="M9,2C7.95,2 6.95,2.16 6,2.46C10.06,3.73 13,7.5 13,12C13,16.5 10.06,20.27 6,21.54C6.95,21.84 7.95,22 9,22A10,10 0 0,0 19,12A10,10 0 0,0 9,2Z" />
              </svg>
            </div>   
        </li>
        <li class="nav-item dropdown">
          <a href="#" class="search-toggle nav-link" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            @php
                $selected_lang_flag = file_exists(public_path('/images/flag/' .app()->getLocale() . '.png')) ? asset('/images/flag/' . app()->getLocale() . '.png') : asset('/images/lang_flag.png');
            @endphp
            <img src="{{ $selected_lang_flag }}" class="img-fluid rounded selected-lang" alt="lang-flag">
            <span class="bg-primary"></span>
          </a>
          <div class="sub-drop dropdown-menu dropdown-menu-end p-0 language-menu" aria-labelledby="dropdownMenuButton2">
            <div class="card shadow-none m-0 border-0">
              <div class=" p-0 ">
                <ul class="list-group list-group-flush">
                @php
                    $language_option = appSettingData('get')->language_option;
                        if(!empty($language_option)){
                            $language_array = languagesArray($language_option);
                        }
                    @endphp
                    @if(count($language_array) > 0 )
                        @foreach( $language_array  as $lang )
                            <li class="iq-sub-card list-group-item">
                                <a class="dropdown-item p-0" data-lang="{{ $lang['id'] }}" href="{{ route('change.language',[ 'locale' => $lang['id'] ]) }}">
                                @php
                                    $flag_path = file_exists(public_path('/images/flag/' . $lang['id'] . '.png')) ? asset('/images/flag/' . $lang['id'] . '.png') : asset('/images/lang_flag.png');
                                @endphp
                                    <img src="{{ $flag_path }}" alt="img-flag-{{ $lang['id'] }}" class="img-fluid me-2 selected-lang-list" />
                                    {{ $lang['title'] }}
                                </a>
                            </li>
                        @endforeach
                    @endif
                </ul>
              </div>
            </div>
          </div>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link py-0 d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="{{ getSingleMedia(auth()->user(),'profile_image', null) }}" alt="User-Profile" class="img-fluid avatar avatar-50 avatar-rounded">
            <div class="caption ms-3 d-none d-md-block ">
              <h6 class="mb-0 caption-title">{{ auth()->user()->display_name }}</h6>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
            <li><a class="dropdown-item" href="{{ route('setting.index',[ 'page' => 'profile-form' ]) }}">{{ __('message.profile') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('setting.index',[ 'page' => 'password-form' ]) }}">{{ __('message.change_password') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('setting.index') }}">{{ __('message.setting') }}</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="POST" action="{{route('logout')}}">
              @csrf
              <a href="javascript:void(0)" class="dropdown-item"
                onclick="event.preventDefault();
              this.closest('form').submit();">
                  {{ __('message.logout') }}
              </a>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>


