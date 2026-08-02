@push('scripts')
    <script>
        (function($) {
            "use strict";
            $(document).ready(function(){
                $(document).on('click','#permissionList .cardheader',function(){
                    if($(this).find('i').hasClass('fa-minus')){
                        $('#permissionList .cardheader i').removeClass('fa-plus').removeClass('fa-minus').addClass('fa-plus');
                        $(this).find('i').addClass('fa-plus').removeClass('fa-minus');
                    }else{
                        $('#permissionList .cardheader i').removeClass('fa-plus').removeClass('fa-minus').addClass('fa-plus');
                        $(this).find('i').removeClass('fa-plus').addClass('fa-minus');
                    }
                });
            });
        })(jQuery);
    </script>
@endpush
<x-app-layout :assets="$assets ?? []">
    <div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h5 class="font-weight-bold">{{ $pageTitle ?? __('message.list') }}</h5>
                            @if($auth_user->can('permission-add'))
                                <a href="#" class="float-end btn btn-sm btn-primary" data-modal-form="form" data-size="small" data--href="{{ route('permission.add',[ 'type' => 'permission' ]) }}" data-app-title="{{ __('message.add_form_title',['form' => __('message.permission')]) }}" data-placement="top">{{ __('message.add_form_title',['form' => __('message.permission')  ]) }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="accordion cursor" id="permissionList">
                    {{ html()->form('POST', route('permission.store'))->open() }} 
                    @foreach($permissions as $key => $data)
                        <?php
                            $a = str_replace("_"," ",$key);
                            $k = ucwords($a);
                        ?>
                        <div class="card">
                            <div class="cardheader d-flex justify-content-between collapsed btn" id="heading_{{$key}}" data-bs-toggle="collapse" data-bs-target="#pr_{{$key}}" aria-expanded="false" aria-controls="pr_{{$key}}">
                                <div class="header-title">
                                    <h6 class="mb-0 text-capitalize"> <i class="fa fa-plus mr-10"></i> {{ $data->name }}<span class="badge badge-secondary"></span></h6>
                                </div>
                            </div>
                            <div id="pr_{{$key}}" class="collapse bg_light_gray" aria-labelledby="heading_{{$key}}" data-parent="#permissionList">
                                <div class="card-body table-responsive">
                                    <table class="table text-center table-bordered bg_white">
                                        <tr>
                                            <th>{{ __('message.name') }}</th>
                                            @foreach($roles as $role)
                                                <th>{{ ucwords(str_replace('_',' ',$role->name)) }}</th>
                                            @endforeach
                                        </tr>
                                        @foreach($data->subpermission as $p)
                                            <tr>
                                                <td class="text-capitalize">{{ $p->name }}</td>
                                                @foreach($roles as $role)
                                                    <td>
                                                        <!-- <input class="checkbox no-wh permission_check" id="permission-{{$role->id}}-{{$p->id}}" type="checkbox" name="permission[{{$p->name}}][]" value='{{$role->name}}' {{ (AuthHelper::checkRolePermission($role,$p->name)) ? 'checked' : '' }} @if($role->is_hidden) disabled @endif > -->
                                                        <input class="form-check-input" type="checkbox" id="permission-{{$role->id}}-{{$p->id}}" name="permission[{{$p->name}}][]" value='{{$role->name}}'
                                                            {{ (AuthHelper::checkRolePermission($role,$p->name)) ? 'checked' : '' }}>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                    <input type="submit" name="Save" value="Save" class="btn btn-md btn-primary float-right mall-10">
                                </div>
                            </div>
                        </div>
                    @endforeach
                    {{ html()->form()->close() }}   
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
