<div class="d-flex align-items-center">
    <a class="btn btn-sm btn-icon btn-warning me-2" href="{{ route('admin-login-device.show', $user_id) }}" title="{{ __('message.view_form_title', [ 'form' => __('message.admin_device_login') ]) }}">
        <span class="btn-inner">
            <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" stroke="currentColor" d="M15.1614 12.0531C15.1614 13.7991 13.7454 15.2141 11.9994 15.2141C10.2534 15.2141 8.83838 13.7991 8.83838 12.0531C8.83838 10.3061 10.2534 8.89111 11.9994 8.89111C13.7454 8.89111 15.1614 10.3061 15.1614 12.0531Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>                                
                <path fill-rule="evenodd" clip-rule="evenodd" d="M11.998 19.355C15.806 19.355 19.289 16.617 21.25 12.053C19.289 7.48898 15.806 4.75098 11.998 4.75098H12.002C8.194 4.75098 4.711 7.48898 2.75 12.053C4.711 16.617 8.194 19.355 12.002 19.355H11.998Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </span>
    </a>

    {{ html()->form('GET', route('admin.device.logout', $id))->attribute('data--submit', 'device-logout' . $id)->open() }} 
        {{ html()->hidden('_method', 'GET') }}
        <a class="btn btn-sm btn-icon btn-danger me-2" href="javascript:void(0)"data-bs-toggle="tooltip"data--submit="device-logout{{ $id }}"
            data--confirmation="true"data-title="{{  __('message.admin_device_login') }}"
            title="{{ __('message.admin_device_logout') }}"
            data-message="{{ __('message.are_you_sure_you_want_to_logout_this_device') }}">
            <span class="btn-inner">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                    <path fill-rule="evenodd"d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0z" />
                    <path fill-rule="evenodd"d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708z" />
                </svg>
            </span>
        </a>
    {{ html()->form()->close() }}
</div>
