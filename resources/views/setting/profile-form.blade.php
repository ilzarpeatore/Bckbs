<div class="col-md-12">
    <div class="row ">
		<div class="col-md-3">
			<div class="user-sidebar">
				<div class="user-body user-profile text-center">
					<div class="user-img">
						<img class="rounded-circle avatar-100 image-fluid profile_image_preview" src="{{ getSingleMedia($user_data,'profile_image', null) }}" alt="profile-pic">
					</div>
					<div class="sideuser-info">
						<span class="mb-2">{{ $user_data->display_name }}</span>
					</div>
				</div>
			</div>
		</div>
		<div class="col-md-9">
			<div class="user-content">
				{{ html()->modelForm($user_data, 'POST', route('updateProfile'))->attribute('data-toggle', 'validator')->attribute('enctype', 'multipart/form-data')->id('user-form')->open() }}
				{{ html()->hidden('id')->placeholder('id')->class('form-control') }}
				<div class="row">
					<div class="form-group col-md-6">
						{{ html()->label(__('message.first_name').' <span class="text-danger">*</span>')->class('form-control-label') }}
						{{ html()->text('first_name', old('first_name'))->placeholder(__('message.first_name'))->class('form-control')->required() }}
					</div>

					<div class="form-group col-md-6">
						{{ html()->label(__('message.last_name').' <span class="text-danger">*</span>')->class('form-control-label') }}
						{{ html()->text('last_name', old('last_name'))->placeholder(__('message.last_name'))->class('form-control')->required() }}
					</div>

					<div class="form-group col-md-6">
						{{ html()->label(__('message.username').' <span class="text-danger">*</span>')->class('form-control-label') }}
						{{ html()->text('username', old('username'))->placeholder(__('message.username'))->class('form-control')->required() }}
					</div>

					<div class="form-group col-md-6">
						{{ html()->label(__('message.email').' <span class="text-danger">*</span>')->class('form-control-label') }}
						{{ html()->email('email', old('email'))->placeholder(__('message.email'))->class('form-control')->required() }}
					</div>

					<div class="form-group col-md-6">
						{{ html()->label(__('message.phone_number'))->class('form-control-label') }}
						{{ html()->text('phone_number', old('phone_number'))->placeholder(__('message.phone_number'))->class('form-control')->id('number') }}
						<small id="error-msg" class="text-danger d-none"></small>
    					<small id="valid-msg" class="text-success d-none"></small>
					</div>

					<div class="form-group col-md-6">
						{{ html()->label(__('message.choose_profile_image'))->class('form-control-label col-md-12') }}
						{{ html()->file('profile_image')->class('form-control')->id('profile_image')->accept('image/*') }}
					</div>

					<div class="col-md-12">
						{{ html()->submit(__('message.update'))->class('btn btn-md btn-primary float-md-end') }}
					</div>
				</div>
			</div>
		</div>
    </div>
</div>

<script>
	$(document).ready(function (){
				
        $(document).on('change','#profile_image',function(){
			readURL(this);
		})
		function readURL(input) {
			if (input.files && input.files[0]) {
				var reader = new FileReader();

				var res=isImage(input.files[0].name);

				if(res==false){
					var msg = "{{ __('message.image_png_jpg') }}";
					Swal.fire({
						icon: 'error',
						title: "{{ __('message.opps') }}",
						text: msg,
						confirmButtonColor: "var(--bs-primary)",
                    	confirmButtonText: "{{ __('message.ok') }}"
					});
					$(input).val("");
					return false;
				}

				reader.onload = function(e) {
				$('.profile_image_preview').attr('src', e.target.result);
					$("#imagelabel").text((input.files[0].name));
				}

				reader.readAsDataURL(input.files[0]);
			}
		}

		function getExtension(filename) {
			var parts = filename.split('.');
			return parts[parts.length - 1];
		}

		function isImage(filename) {
			var ext = getExtension(filename);
			switch (ext.toLowerCase()) {
			case 'jpg':
			case 'jpeg':
			case 'png':
			case 'gif':
				return true;
			}
			return false;
		}
	})
</script>