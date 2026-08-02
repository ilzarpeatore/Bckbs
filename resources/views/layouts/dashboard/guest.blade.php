<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ mighty_language_direction() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="shortcut icon" class="site_favicon_preview" href="{{ getSingleMedia(appSettingData('get'), 'site_favicon', null) }}" />
        
        <!-- Fonts -->
        <!-- <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap"> -->

        <!-- Styles -->
        <link rel="stylesheet" href="{{ asset('css/libs.min.css') }}">
        <link rel="stylesheet" href="{{asset('css/hope-ui.css?v=1.0')}}">
        @if(mighty_language_direction() == 'rtl')
            <link rel="stylesheet" href="{{ asset('css/rtl.css') }}">
        @endif
        <!-- remixicon -->
        <!-- <link rel="stylesheet" href="{{ asset('vendor/remixicon/fonts/remixicon.css') }}"/> -->

    </head>
    <body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
        <div id="loading">
            @include('partials.dashboard._body_loader')
            @include('partials.dashboard._head')
        </div>
        <div class="wrapper">
            {{ $slot }}
        </div>
         @include('partials.dashboard._scripts')
    </body>
</html>
