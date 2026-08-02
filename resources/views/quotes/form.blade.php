@push('scripts')
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {{ html()->modelForm($data, 'PATCH', route('quotes.update', $id) )->open() }}
        @else
            {{ html()->form('POST', route('quotes.store'))->open() }} 
        @endif
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                        <div class="card-action">
                            <a href="{{ route('quotes.index') }} " class="btn btn-sm btn-primary" role="button">{{ __('message.back') }}</a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">

                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.title') . ' <span class="text-danger">*</span>', 'title')->class('form-control-label') }}
                                {{ html()->text('title')->placeholder(__('message.title'))->class('form-control')->attribute('required', 'required') }}
                            </div>
                            <div class="form-group col-md-6">
                                {{ html()->label(__('message.date') . ' <span class="text-danger">*</span>', 'date')->class('form-control-label') }}
                                {{ html()->text('date')->placeholder(__('message.date'))->class('datepicker form-control')->attribute('required', 'required') }}
                            </div>
                            <div class="form-group col-md-12">
                                {{ html()->label(__('message.message') . ' <span class="text-danger">*</span>', 'message')->class('form-control-label') }}
                                {{ html()->textarea('message')->rows(3)->class('form-control textarea')->attribute('required', 'required')->placeholder(__('message.message')) }}
                            </div>                            
                        </div>
                        <hr>
                        {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
                    </div>
                </div>
            </div>
        </div>
        @if(isset($id))
            {{ html()->closeModelForm() }}
        @else
            {{ html()->form()->close() }}
        @endif
    </div>
</x-app-layout>
