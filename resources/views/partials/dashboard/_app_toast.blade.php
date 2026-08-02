<script type="text/javascript">
    {{-- Success Message --}}
    @if (Session::has('success'))
        Swal.fire({
            icon: 'success',
            title: "{{ __('message.done') }}",
            text: '{{ Session::get("success") }}',
            confirmButtonColor: "var(--bs-primary)",
            confirmButtonText: "{{ __('message.ok') }}"
        });
    @endif
    {{-- Errors Message --}}
    @if (Session::has('error'))
        Swal.fire({
            icon: 'error',
            title: "{{ __('message.opps') }}",
            text: '{{Session::get("error")}}',
            confirmButtonColor: "var(--bs-primary)",
            confirmButtonText: "{{ __('message.ok') }}"
        });
    @endif
    @if(Session::has('errors') || ( isset($errors) && is_array($errors) && $errors->any()))
        Swal.fire({
            icon: 'error',
            title: "{{ __('message.opps') }}",
            text: '{{Session::get("errors")->first() }}',
            confirmButtonColor: "var(--bs-primary)",
            confirmButtonText: "{{ __('message.ok') }}"
        });
    @endif
</script>