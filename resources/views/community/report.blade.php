{{ html()->form('POST', route('posting.report'))->attribute('data-toggle', 'validator')->open() }} 
<div class="row">
    <input type="hidden" name="posting_id" value="{{  $data['posting_id'] ?? '' }}">
    <div class="form-group col-md-12">
        {{ html()->label(__('message.reason') . ' <span class="text-danger">*</span>', 'reason')->class('form-control-label') }}
        {{ html()->textarea('reason')->class('form-control textarea')->rows(3)->attribute('required', 'required')->placeholder(__('message.reason')) }}
    </div>
</div>

<div class="modal-footer mt-1">
    @if( auth()->check() )
        <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">{{ __('message.cancel') }}</button>
        <button type="submit" class="btn btn-md btn-primary" id="btn_submit" data-comment-form="ajax" >{{ __('frontend::message.report') }}</button>
    @else
        <div class="d-flex justify-content-center">
            <a href="{{ route('frontend.signin') }}" class="btn btn-primary">
                {{ __('auth.sign_in') }}
            </a>
        </div>
    @endif
</div>
{{ html()->form()->close() }}

