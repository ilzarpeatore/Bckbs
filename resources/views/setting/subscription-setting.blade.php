{{ html()->form('POST', route('subscriptionSettingsUpdate'))->attribute('data-toggle', 'validator')->open() }} 
    {{ html()->hidden('id')->value(null)->class('form-control') }}
    {{ html()->hidden('page')->value($page)->class('form-control') }}
    <div class="row">
        <div class="col-lg-12">
            <div class="form-group">
                {{ html()->label(__('message.subscription_system') . ' <span class="text-danger">*</span>', 'subscription_system')->class('form-control-label') }}
                {{ html()->select('subscription_system')->options([ '1' => __('message.yes'), '0' => __('message.no') ])->value(old('subscription_system', $settings ?? '1'))->class('form-control select2js')->attribute('required', 'required') }}                
            </div>
        </div>
    </div>
    {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
    @if(isset($id))
        {{ html()->closeModelForm() }}
    @else
        {{ html()->form()->close() }}
    @endif
<script>
    $(document).ready(function() {
        $('.select2js').select2();
    });
</script>