<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
    @if ($level === 'error')
        # @lang('Whoops!')
    @else
        # @lang('Hello!')
    @endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{!! nl2br(e($salutation)) !!}
@else
@lang('Regards,')<br>
{{ config('app.name') }}
@endif
</x-mail::message>
