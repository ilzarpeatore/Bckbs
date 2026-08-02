@if( !in_array($role->name, ['user']) )
<div class="form-group">
    <div class="form-check form-switch">
        <input class="form-check-input change_status" type="checkbox" data-type="role" id="{{ $role->id }}" data-id="{{ $role->id }}" {{ $role->status ? 'checked' : '' }} value = "{{ $role->id }}">
        <label class="form-check-label" for="{{ $role->id }}"></label>
    </div>
</div>
@endif