{{ html()->form('POST', '#')->open() }}
    <div class="form-group">
        <label class="form-label">permission title</label>
        {{ html()->text('title', old('title'))->class('form-control')->id('permission-title')->placeholder('Permission Title')->required() }}
    </div>
    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Save</button>
    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
{{ html()->form()->close() }}