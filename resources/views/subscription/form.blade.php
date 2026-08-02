<!-- Modal -->
{{ html()->form('POST', route('subscription.store'))->attribute('data-toggle', 'validator')->open() }} 
    <div class="row">
        <div class="form-group col-md-12">
            {{ html()->label(__('message.user').' <span class="text-danger">*</span>')->class('form-control-label') }}
            {{ html()->select('user_id[]', $users ?? [], old('user_id'))
                ->class('select2js-modal form-group')
                ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.user')]))
                ->attribute('required', 'required')
                ->attribute('multiple', 'multiple')
            }}            
        </div>
        <div class="form-group col-md-12">
            {{ html()->label(__('message.package').' <span class="text-danger">*</span>')->class('form-control-label') }}
            {{ html()->select('package_id', [], old('package_id'))
                ->class('select2js-modal form-group')
                ->attribute('data-placeholder', __('message.select_name', ['select' => __('message.package')]))
                ->attribute('data-ajax--url', route('ajax-list', ['type' => 'package']))
                ->attribute('required', 'required')
            }}            
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">{{ __('message.close') }}</button>
        <button type="submit" class="btn btn-md btn-primary" id="btn_submit" data-form="ajax" >{{ __('message.save') }}</button>
    </div>
    @if(isset($id))
    {{ html()->closeModelForm() }}
    @else
        {{ html()->form()->close() }}
    @endif
<script>
    $('.select2js-modal').select2({
        dropdownParent: $('#formModal'),
        width: '100%',
    });
</script>

