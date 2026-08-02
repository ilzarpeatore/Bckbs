

<!-- Favicon -->
<link rel="shortcut icon" class="site_favicon_preview" href="{{ getSingleMedia(appSettingData('get'), 'site_favicon', null) }}" />
<link rel="stylesheet" href="{{asset('css/libs.min.css')}}">
<link rel="stylesheet" href="{{asset('css/hope-ui.css?v=1.1.0')}}">
<link rel="stylesheet" href="{{ asset('vendor/confirmJS/jquery-confirm.min.css') }}"/>
<link rel="stylesheet" href="{{asset('css/custom.css?v=1.1.0')}}">
<link rel="stylesheet" href="{{asset('css/customizer.css?v=1.1.0')}}">
<link rel="stylesheet" href="{{asset('css/dark.css?v=1.1.0')}}">
@if(mighty_language_direction() == 'rtl')
   <link rel="stylesheet" href="{{asset('css/rtl.css?v=1.1.0')}}">
@endif

<link rel="stylesheet" href="{{ asset('vendor/intlTelInput/css/intlTelInput.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}">

<!-- Fullcalender CSS -->
<link rel='stylesheet' href="{{asset('vendor/fullcalendar/core/main.css')}}" />
<link rel='stylesheet' href="{{asset('vendor/fullcalendar/daygrid/main.css')}}" />
<link rel='stylesheet' href="{{asset('vendor/fullcalendar/timegrid/main.css')}}" />
<link rel='stylesheet' href="{{asset('vendor/fullcalendar/list/main.css')}}" />
<link rel="stylesheet" href="{{asset('vendor/Leaflet/leaflet.css')}}" />
<link rel="stylesheet" href="{{asset('css/custom-css.css') }}">

<link rel="stylesheet" href="{{asset('vendor/aos/dist/aos.css')}}" />

<style>
    th.hide-search input{
       display: none;
    }
 </style>
