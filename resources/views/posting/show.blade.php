@push('scripts')
    {{ $dataTable->scripts() }}
    <script>
        var swiper = new Swiper(".posting-media", {
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
        });
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h5 class="font-weight-bold">{{ $pageTitle }}</h5>
                            <div class="">
                                <a class="btn btn-sm btn-danger" href="{{ route('posting.destroy', $data->id) }}"
                                    data--submit='confirm_form' data--confirmation='true' data--ajax='delete' data-toggle='tooltip'
                                    data-title="{{ __('message.delete_form_title',['form'=> __('message.posting') ]) }}"
                                    data-message='{{ __("message.delete_alert", ["form" =>  __("message.posting") ]) }}'>
                                    {{ __('message.delete') }}
                                </a>
                                <a href="{{ route('posting.index') }}" class="float-right ml-1 btn btn-sm btn-primary">{{ __('message.back') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8">
                <div class="profile-content tab-content">
                    <div id="profile-feed" class="tab-pane fade active show" role="tabpanel">
                        <x-posting-card :post="$data" />
                    </div>
                </div>
            </div>
        </div>

        @if( $auth_user->can('reported-posting-list') )
            <div class="row">
                <div class="col-lg-12">
                    <div class="card card-block">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title mb-0">{{ __('message.list_form_title', [ 'form' => __('message.reported_on_post_by_user') ]) }}</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            {{ $dataTable->table(['class' => 'table  w-100'],false) }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
   
</x-app-layout>
