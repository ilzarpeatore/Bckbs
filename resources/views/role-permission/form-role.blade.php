{{ html()->form('POST', '#')->open() }}
    <div class="form-group">
        {{ html()->label('Role Title', 'title')->class('form-label') }}
        {{ html()->text('title', old('title'))->class('form-control')->id('role-title')->placeholder('Role Title')->required() }}
    </div>
    {{ html()->label('Status')->class('form-label') }}
    <div class="form-check">
        {{ html()->radio('status', '1', old('status') == '1')->class('form-check-input')->id('roleassigned') }}
        {{ html()->label('Yes', 'roleassigned')->class('form-check-label') }}
    </div>
    <div class="mb-3 form-check">
        {{ html()->radio('status', '0', old('status') == '0')->class('form-check-input')->id('rolenotassigned') }}
        {{ html()->label('No', 'rolenotassigned')->class('form-check-label') }}
    </div>
    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Save</button>
    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
{{ html()->form()->close() }}