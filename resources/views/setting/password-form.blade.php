
{{ html()->form('POST', route('changePassword'))->attributes(['data-toggle' => 'validator', 'id' => 'user-password'])->open() }}

<div class="row">
        <div class="col-md-6 offset-md-3">
            {{ html()->hidden('id') }}

            <div class="form-group has-feedback">
                {{ html()->label(__('message.old_password') . ' <span class="text-danger">*</span>', 'old_password')->class('form-control-label col-md-12') }}
                <div class="col-md-12">
                    {{ html()->password('old')->class('form-control')->id('old_password')->placeholder(__('message.old_password'))->required() }}
                </div>
            </div>
            <div class="form-group has-feedback">
                {{ html()->label(__('message.new_password') . ' <span class="text-danger">*</span>', 'password')->class('form-control-label col-md-12') }}
                <div class="col-md-12">
                    {{ html()->password('password')->class('form-control')->id('password')->placeholder(__('message.new_password'))->required() }}
                </div>
            </div>
            <div class="form-group has-feedback">
                {{ html()->label(__('message.confirm_new_password') . ' <span class="text-danger">*</span>', 'password-confirm')->class('form-control-label col-md-12') }}
                <div class="col-md-12">
                    {{ html()->password('password_confirmation')->class('form-control')->id('password-confirm')->placeholder(__('message.confirm_new_password'))->required() }}
                </div>
            </div>

            <div class="form-group">
                <div class="col-md-12">
                    {{ html()->submit(__('message.save'))->class('btn btn-md btn-primary float-md-end mt-15')->id('submit') }}
                </div>
            </div>
        </div>
</div>
{{ html()->form()->close() }}   