{{-- Email con un botón de acción (confirmar newsletter, retomar una compra...). --}}
<x-mail::message>
# {{ $greeting }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach
@if (!empty($actionUrl))
<x-mail::button :url="$actionUrl" color="success">
{{ $actionText }}
</x-mail::button>
@endif

@foreach ($outroLines as $line)
{{ $line }}

@endforeach
{{ config('app.name') }}
@if (!empty($footerNote))

<small>{{ $footerNote }}</small>
@endif
</x-mail::message>
