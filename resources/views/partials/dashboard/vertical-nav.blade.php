@php
    $url = '';

    $MyNavBar = \Menu::make('MenuList', function ($menu) use($url){

        //Admin Dashboard
        $menu->add('<span class="item-name">'.__('message.dashboard').'</span>', ['route' => 'dashboard'])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.5 15.5C13.5 13.6144 13.5 12.6716 14.0858 12.0858C14.6716 11.5 15.6144 11.5 17.5 11.5C19.3856 11.5 20.3284 11.5 20.9142 12.0858C21.5 12.6716 21.5 13.6144 21.5 15.5V17.5C21.5 19.3856 21.5 20.3284 20.9142 20.9142C20.3284 21.5 19.3856 21.5 17.5 21.5C15.6144 21.5 14.6716 21.5 14.0858 20.9142C13.5 20.3284 13.5 19.3856 13.5 17.5V15.5Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M2 8.5C2 10.3856 2 11.3284 2.58579 11.9142C3.17157 12.5 4.11438 12.5 6 12.5C7.88562 12.5 8.82843 12.5 9.41421 11.9142C10 11.3284 10 10.3856 10 8.5V6.5C10 4.61438 10 3.67157 9.41421 3.08579C8.82843 2.5 7.88562 2.5 6 2.5C4.11438 2.5 3.17157 2.5 2.58579 3.08579C2 3.67157 2 4.61438 2 6.5V8.5Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M13.5 5.5C13.5 4.56812 13.5 4.10218 13.6522 3.73463C13.8552 3.24458 14.2446 2.85523 14.7346 2.65224C15.1022 2.5 15.5681 2.5 16.5 2.5H18.5C19.4319 2.5 19.8978 2.5 20.2654 2.65224C20.7554 2.85523 21.1448 3.24458 21.3478 3.73463C21.5 4.10218 21.5 4.56812 21.5 5.5C21.5 6.43188 21.5 6.89782 21.3478 7.26537C21.1448 7.75542 20.7554 8.14477 20.2654 8.34776C19.8978 8.5 19.4319 8.5 18.5 8.5H16.5C15.5681 8.5 15.1022 8.5 14.7346 8.34776C14.2446 8.14477 13.8552 7.75542 13.6522 7.26537C13.5 6.89782 13.5 6.43188 13.5 5.5Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M2 18.5C2 19.4319 2 19.8978 2.15224 20.2654C2.35523 20.7554 2.74458 21.1448 3.23463 21.3478C3.60218 21.5 4.06812 21.5 5 21.5H7C7.93188 21.5 8.39782 21.5 8.76537 21.3478C9.25542 21.1448 9.64477 20.7554 9.84776 20.2654C10 19.8978 10 19.4319 10 18.5C10 17.5681 10 17.1022 9.84776 16.7346C9.64477 16.2446 9.25542 15.8552 8.76537 15.6522C8.39782 15.5 7.93188 15.5 7 15.5H5C4.06812 15.5 3.60218 15.5 3.23463 15.6522C2.74458 15.8552 2.35523 16.2446 2.15224 16.7346C2 17.1022 2 17.5681 2 18.5Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg></i>')
            ->link->attr([ 'class' => activeRoute(route('dashboard')) ? 'nav-link active' : 'nav-link' ]);

        $menu->add('<span class="item-name">'.__('message.user').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="9" cy="6" r="4" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M15 9C16.6569 9 18 7.65685 18 6C18 4.34315 16.6569 3 15 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <ellipse cx="9" cy="17" rx="7" ry="4" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M18 14C19.7542 14.3847 21 15.3589 21 16.5C21 17.5293 19.9863 18.4229 18.5 18.8704" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg></i>')
            ->nickname('user')
            ->data('permission', 'user-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#user');

            $menu->user->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.user')]).'</span>', ['route' => 'users.index'])
                ->data('permission', 'user-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('users.index')) || request()->routeIs('users.show')  ? 'nav-link active' : 'nav-link']);

            $menu->user->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.user')]).'</span>', ['route' => 'users.create'])
                ->data('permission', [ 'user-add', 'user-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('users.create') || request()->routeIs('users.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.sub_admin').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                <svg class="icon-24" width="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.87651 15.2063C6.03251 15.2063 2.74951 15.7873 2.74951 18.1153C2.74951 20.4433 6.01251 21.0453 9.87651 21.0453C13.7215 21.0453 17.0035 20.4633 17.0035 18.1363C17.0035 15.8093 13.7415 15.2063 9.87651 15.2063Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.8766 11.886C12.3996 11.886 14.4446 9.841 14.4446 7.318C14.4446 4.795 12.3996 2.75 9.8766 2.75C7.3546 2.75 5.3096 4.795 5.3096 7.318C5.3006 9.832 7.3306 11.877 9.8456 11.886H9.8766Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M19.2036 8.66919V12.6792" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M21.2497 10.6741H17.1597" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg></i>')
            ->nickname('sub_admin')
            ->data('permission', 'subadmin-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#sub_admin');

            $menu->sub_admin->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.sub_admin')]).'</span>', ['route' => 'subadmin.index'])
                ->data('permission', 'subadmin-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('subadmin.index')) ? 'nav-link active' : 'nav-link']);

            $menu->sub_admin->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.sub_admin')]).'</span>', ['route' => 'subadmin.create'])
                ->data('permission', [ 'subadmin-add', 'subadmin-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('subadmin.create')) || request()->routeIs('subadmin.edit') ? 'nav-link active' : 'nav-link']);

            if (auth()->user() && auth()->user()->hasRole('admin')) {
                $menu->add('<span class="item-name">' . __('message.admin_login_history') . '</span>', ['route' => 'admin-login-history.index'])
                    ->prepend('<i class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-hourglass-split" viewBox="0 0 16 16">
                            <path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 13v1h1a.5.5 0 0 1 0 1zm2-13v1c0 .537.12 1.045.337 1.5h6.326c.216-.455.337-.963.337-1.5V2zm3 6.35c0 .701-.478 1.236-1.011 1.492A3.5 3.5 0 0 0 4.5 13s.866-1.299 3-1.48zm1 0v3.17c2.134.181 3 1.48 3 1.48a3.5 3.5 0 0 0-1.989-3.158C8.978 9.586 8.5 9.052 8.5 8.351z"/>
                        </svg>
                    </i>')
                     ->link->attr(['class' => activeRoute(route('admin-login-history.index')) || request()->routeIs('admin-login-history.index') ? 'nav-link active' : 'nav-link']);
            }

        $menu->add('<span class="item-name">'.__('message.equipment').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8.32031 12.1982L12.2003 8.31823M15.3043 11.4222L11.4243 15.3023" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M3.43157 15.6193C2.52737 14.7151 2.07528 14.263 2.0108 13.7109C1.9964 13.5877 1.9964 13.4632 2.0108 13.3399C2.07528 12.7879 2.52737 12.3358 3.43156 11.4316C4.33575 10.5274 4.78785 10.0753 5.33994 10.0108C5.46318 9.9964 5.58768 9.9964 5.71092 10.0108C6.26301 10.0753 6.71511 10.5274 7.6193 11.4316L12.5684 16.3807C13.4726 17.2849 13.9247 17.737 13.9892 18.2891C14.0036 18.4123 14.0036 18.5368 13.9892 18.6601C13.9247 19.2122 13.4726 19.6642 12.5684 20.5684C11.6642 21.4726 11.2122 21.9247 10.6601 21.9892C10.5368 22.0036 10.4123 22.0036 10.2891 21.9892C9.73699 21.9247 9.28489 21.4726 8.3807 20.5684L3.43157 15.6193Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M11.4316 7.6193C10.5274 6.71511 10.0753 6.26301 10.0108 5.71092C9.9964 5.58768 9.9964 5.46318 10.0108 5.33994C10.0753 4.78785 10.5274 4.33576 11.4316 3.43156C12.3358 2.52737 12.7879 2.07528 13.3399 2.0108C13.4632 1.9964 13.5877 1.9964 13.7109 2.0108C14.263 2.07528 14.7151 2.52737 15.6193 3.43156L20.5684 8.3807C21.4726 9.28489 21.9247 9.73699 21.9892 10.2891C22.0036 10.4123 22.0036 10.5368 21.9892 10.6601C21.9247 11.2122 21.4726 11.6642 20.5684 12.5684C19.6642 13.4726 19.2122 13.9247 18.6601 13.9892C18.5368 14.0036 18.4123 14.0036 18.2891 13.9892C17.737 13.9247 17.2849 13.4726 16.3807 12.5684L11.4316 7.6193Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M18.0195 2.49805L21.1235 5.60206" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2.49609 18.0181L5.6001 21.1221" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg></i>')
            ->nickname('equipment')
            ->data('permission', 'equipment-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#equipment');

            $menu->equipment->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.equipment')]).'</span>', ['route' => 'equipment.index'])
                ->data('permission', 'equipment-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('equipment.index')) ? 'nav-link active' : 'nav-link']);

            $menu->equipment->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.equipment')]).'</span>', ['route' => 'equipment.create'])
                ->data('permission', [ 'equipment-add', 'equipment-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('equipment.create')) || request()->routeIs('equipment.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.exercise').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14.1009 2.64567C14.1009 3.69258 13.2522 4.54134 12.2051 4.54134C11.1581 4.54134 10.3093 3.69258 10.3093 2.64567C10.3093 1.59876 11.1581 0.75 12.2051 0.75C13.2522 0.75 14.1009 1.59876 14.1009 2.64567Z" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M1.99902 8.50402V7.00612C1.99902 6.58326 2.3459 6.24263 2.76869 6.25032L12.3933 6.4253L22.2071 6.25007C22.6298 6.24252 22.9765 6.58311 22.9765 7.00587V8.50402C22.9765 8.92151 22.6381 9.25995 22.2206 9.25995H16.4162C15.9408 9.25995 15.5834 9.69361 15.6742 10.1603L17.9978 22.0998C18.0886 22.5664 17.7312 23.0001 17.2558 23.0001H16.2843C15.9478 23.0001 15.6518 22.7777 15.5582 22.4544L13.6852 15.9867C13.5916 15.6635 13.2956 15.4411 12.9591 15.4411H11.8413C11.4983 15.4411 11.1983 15.672 11.1105 16.0036L9.40821 22.4375C9.32047 22.7691 9.02045 23.0001 8.67743 23.0001H7.70562C7.23489 23.0001 6.87864 22.5745 6.96149 22.1111L9.10041 10.1489C9.18326 9.68555 8.82701 9.25995 8.35628 9.25995H2.75495C2.33746 9.25995 1.99902 8.92151 1.99902 8.50402Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg></i>')
            ->nickname('exercise')
            ->data('permission', 'exercise-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#exercise');

            $menu->exercise->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.exercise')]).'</span>', ['route' => 'exercise.index'])
                ->data('permission', 'exercise-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('exercise.index')) ? 'nav-link active' : 'nav-link']);

            $menu->exercise->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.exercise')]).'</span>', ['route' => 'exercise.create'])
                ->data('permission', [ 'exercise-add', 'exercise-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('exercise.create')) || request()->routeIs('exercise.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.workout').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="15" cy="4" r="2" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M11 16.0002V14.3667C11 13.8177 10.7561 13.297 10.3344 12.9455L9.33793 12.1152C8.61946 11.5164 8.57018 10.43 9.2315 9.76871L10.8855 8.11473C11.4193 7.5809 11.2452 6.67671 10.5513 6.37932C9.26627 5.82861 7.79304 5.94205 6.60752 6.68301L4.5 8.00021" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M7 14L6.67157 14.3284C6.09351 14.9065 5.80448 15.1955 5.43694 15.3478C5.0694 15.5 4.66065 15.5 3.84315 15.5H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M12.5 10H15.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M19.4888 22.0001H3.08684C2.48659 22.0001 2 21.5135 2 20.9133C2 20.3853 2.37943 19.9337 2.89949 19.8427L19.0559 17.0153C20.5926 16.7464 22 17.9289 22 19.4889C22 20.8758 20.8757 22.0001 19.4888 22.0001Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                            <path d="M19.2916 8.88902L18.5499 8.77777L18.5499 8.77777L19.2916 8.88902ZM20.8773 7.22454L21.0244 7.95998L21.0244 7.95998L20.8773 7.22454ZM22.1471 7.73544C22.5533 7.6542 22.8167 7.25908 22.7354 6.85291C22.6542 6.44674 22.2591 6.18333 21.8529 6.26456L22.1471 7.73544ZM18.7417 17.6113L20.0333 9.00028L18.5499 8.77777L17.2583 17.3887L18.7417 17.6113ZM21.0244 7.95998L22.1471 7.73544L21.8529 6.26456L20.7302 6.48911L21.0244 7.95998ZM20.0333 9.00028C20.0862 8.64782 20.1178 8.44487 20.1568 8.2985C20.1744 8.23252 20.1885 8.19883 20.1965 8.18288C20.2002 8.17549 20.2024 8.17218 20.2029 8.17144C20.2034 8.17082 20.2034 8.17074 20.2037 8.17041L19.1177 7.13579C18.8906 7.37412 18.7782 7.64686 18.7076 7.91172C18.6418 8.15825 18.5978 8.45884 18.5499 8.77777L20.0333 9.00028ZM20.7302 6.48911C20.414 6.55235 20.1159 6.61086 19.8728 6.68852C19.6117 6.77197 19.3447 6.89746 19.1177 7.13579L20.2037 8.17041C20.2041 8.17009 20.2041 8.17 20.2047 8.16955C20.2054 8.169 20.2086 8.1666 20.2159 8.16256C20.2314 8.15385 20.2644 8.13813 20.3294 8.11734C20.4737 8.07123 20.6749 8.02987 21.0244 7.95998L20.7302 6.48911Z" fill="currentColor"/>
                    </svg></i>')
            ->nickname('workout')
            ->data('permission', 'workout-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#workout');

            $menu->workout->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.workout')]).'</span>', ['route' => 'workout.index'])
                ->data('permission', 'workout-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('workout.index')) ? 'nav-link active' : 'nav-link']);

            $menu->workout->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.workout')]).'</span>', ['route' => 'workout.create'])
                ->data('permission', [ 'workout-add', 'workout-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('workout.create')) || request()->routeIs('workout.edit') ? 'nav-link active' : 'nav-link']);

            $menu->workout->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.workouttype')]).'</span>', ['route' => 'workouttype.index'])
                ->data('permission', 'workouttype-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('workouttype.*') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.diet').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g>
                            <rect fill="none" height="24" width="24" />
                            <path d="M8.16,11c-1.43,0.07-3.52,0.57-4.54,2h6.55L8.16,11z" enable-background="new" opacity=".3" />
                            <path d="M1,21h15.01v0.98c0,0.56-0.45,1.01-1.01,1.01H2.01C1.45,22.99,1,22.54,1,21.98V21z M20.49,23.31L16,18.83V19H1v-2h13.17 l-2-2H1c0-3.24,2.46-5.17,5.38-5.79l-5.7-5.7L2.1,2.1L13,13l2,2l6.9,6.9L20.49,23.31z M10.17,13l-2-2c-1.42,0.06-3.52,0.56-4.55,2 H10.17z M23,5h-5V1h-2v4h-5l0.23,2h9.56l-1,9.97l1.83,1.83L23,5z" fill="currentColor"/>
                        </g>
                    </svg></i>')
            ->nickname('diet')
            ->data('permission', 'diet-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#diet');

            $menu->diet->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.diet')]).'</span>', ['route' => 'diet.index'])
                ->data('permission', 'diet-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('diet.index')) ? 'nav-link active' : 'nav-link']);

            $menu->diet->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.diet')]).'</span>', ['route' => 'diet.create'])
                ->data('permission', [ 'diet-add', 'diet-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('diet.create') || request()->routeIs('diet.edit') ? 'nav-link active' : 'nav-link']);

            $menu->diet->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.categorydiet')]).'</span>', ['route' => 'categorydiet.index'])
                ->data('permission', 'categorydiet-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('categorydiet.index')) || request()->routeIs('categorydiet.create') || request()->routeIs('categorydiet.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.level').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 22H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3 11C3 10.0572 3 9.58579 3.29289 9.29289C3.58579 9 4.05719 9 5 9C5.94281 9 6.41421 9 6.70711 9.29289C7 9.58579 7 10.0572 7 11V17C7 17.9428 7 18.4142 6.70711 18.7071C6.41421 19 5.94281 19 5 19C4.05719 19 3.58579 19 3.29289 18.7071C3 18.4142 3 17.9428 3 17V11Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M10 7C10 6.05719 10 5.58579 10.2929 5.29289C10.5858 5 11.0572 5 12 5C12.9428 5 13.4142 5 13.7071 5.29289C14 5.58579 14 6.05719 14 7V17C14 17.9428 14 18.4142 13.7071 18.7071C13.4142 19 12.9428 19 12 19C11.0572 19 10.5858 19 10.2929 18.7071C10 18.4142 10 17.9428 10 17V7Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M17 4C17 3.05719 17 2.58579 17.2929 2.29289C17.5858 2 18.0572 2 19 2C19.9428 2 20.4142 2 20.7071 2.29289C21 2.58579 21 3.05719 21 4V17C21 17.9428 21 18.4142 20.7071 18.7071C20.4142 19 19.9428 19 19 19C18.0572 19 17.5858 19 17.2929 18.7071C17 18.4142 17 17.9428 17 17V4Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg></i>')
            ->nickname('level')
            ->data('permission', 'level-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#level');

            $menu->level->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.level')]).'</span>', ['route' => 'level.index'])
                ->data('permission', 'level-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('level.index')) ? 'nav-link active' : 'nav-link']);

            $menu->level->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.level')]).'</span>', ['route' => 'level.create'])
                ->data('permission', [ 'level-add', 'level-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('level.create') || request()->routeIs('level.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.bodypart').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.38892 13.1614C8.22254 12.0779 12.9999 11.0891 16.6405 14.8096" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M2 10.5588C5.1153 10.2051 6.39428 10.6706 8.75105 12.2516M15.5021 13.7518L13.8893 6.66258C13.8186 6.35178 13.4791 6.18556 13.1902 6.32034L10.3819 7.6308C10.1384 7.74442 9.85788 7.75857 9.61117 7.65213C8.87435 7.33425 8.38405 6.97152 7.86685 6.31394C7.61986 5.99992 7.54201 5.5868 7.62818 5.19668C7.87265 4.0899 8.12814 3.34462 8.62323 2.31821C8.70119 2.15659 8.86221 2.05093 9.04141 2.04171C11.0466 1.93856 12.3251 2.01028 14.2625 2.44371C14.5804 2.51485 14.8662 2.69558 15.0722 2.948C19.8635 8.8193 21.3943 11.9968 21.9534 16.6216C21.9872 16.9004 21.8964 17.1818 21.7073 17.3895C17.6861 21.8064 14.7759 22.3704 8.75105 20.0604C6.65624 21.5587 5.07425 21.8624 2.25004 21.3106" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg></i>')
                ->nickname('bodypart')
                ->data('permission', 'bodyparts-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#bodypart');

            $menu->bodypart->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.bodypart')]).'</span>', ['route' => 'bodypart.index'])
                ->data('permission', 'bodyparts-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('bodypart.index')) ? 'nav-link active' : 'nav-link']);

            $menu->bodypart->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.bodypart')]).'</span>', ['route' => 'bodypart.create'])
                ->data('permission', [ 'bodyparts-add', 'bodyparts-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('bodypart.create') || request()->routeIs('bodypart.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.product').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 3L2.26491 3.0883C3.58495 3.52832 4.24497 3.74832 4.62248 4.2721C5 4.79587 5 5.49159 5 6.88304V9.5C5 12.3284 5 13.7426 5.87868 14.6213C6.75736 15.5 8.17157 15.5 11 15.5H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M7.5 18C8.32843 18 9 18.6716 9 19.5C9 20.3284 8.32843 21 7.5 21C6.67157 21 6 20.3284 6 19.5C6 18.6716 6.67157 18 7.5 18Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M16.5 18.0001C17.3284 18.0001 18 18.6716 18 19.5001C18 20.3285 17.3284 21.0001 16.5 21.0001C15.6716 21.0001 15 20.3285 15 19.5001C15 18.6716 15.6716 18.0001 16.5 18.0001Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 6H16.4504C18.5054 6 19.5328 6 19.9775 6.67426C20.4221 7.34853 20.0173 8.29294 19.2078 10.1818L18.7792 11.1818C18.4013 12.0636 18.2123 12.5045 17.8366 12.7523C17.4609 13 16.9812 13 16.0218 13H5" stroke="currentColor" stroke-width="1.5"/>
                        </svg></i>')
                ->nickname('product')
                ->data('permission', 'product-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#product');

            $menu->product->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.product')]).'</span>', ['route' => 'product.index'])
                ->data('permission', 'product-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('product.index')) ? 'nav-link active' : 'nav-link']);

            $menu->product->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.product')]).'</span>', ['route' => 'product.create'])
                ->data('permission', [ 'product-add', 'product-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('product.create')) || request()->routeIs('product.edit') ? 'nav-link active' : 'nav-link']);

            $menu->product->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.productcategory')]).'</span>', ['route' => 'productcategory.index'])
                ->data('permission', 'productcategory-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('productcategory.*') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.post').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18.18 8.03933L18.6435 7.57589C19.4113 6.80804 20.6563 6.80804 21.4241 7.57589C22.192 8.34374 22.192 9.58868 21.4241 10.3565L20.9607 10.82M18.18 8.03933C18.18 8.03933 18.238 9.02414 19.1069 9.89309C19.9759 10.762 20.9607 10.82 20.9607 10.82M18.18 8.03933L13.9194 12.2999C13.6308 12.5885 13.4865 12.7328 13.3624 12.8919C13.2161 13.0796 13.0906 13.2827 12.9882 13.4975C12.9014 13.6797 12.8368 13.8732 12.7078 14.2604L12.2946 15.5L12.1609 15.901M20.9607 10.82L16.7001 15.0806C16.4115 15.3692 16.2672 15.5135 16.1081 15.6376C15.9204 15.7839 15.7173 15.9094 15.5025 16.0118C15.3203 16.0986 15.1268 16.1632 14.7396 16.2922L13.5 16.7054L13.099 16.8391M13.099 16.8391L12.6979 16.9728C12.5074 17.0363 12.2973 16.9867 12.1553 16.8447C12.0133 16.7027 11.9637 16.4926 12.0272 16.3021L12.1609 15.901M13.099 16.8391L12.1609 15.901" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M8 13H10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M8 9H14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M8 17H9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M19.8284 3.17157C18.6569 2 16.7712 2 13 2H11C7.22876 2 5.34315 2 4.17157 3.17157C3 4.34315 3 6.22876 3 10V14C3 17.7712 3 19.6569 4.17157 20.8284C5.34315 22 7.22876 22 11 22H13C16.7712 22 18.6569 22 19.8284 20.8284C20.7715 19.8853 20.9554 18.4796 20.9913 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg></i>')
                ->nickname('post')
                ->data('permission', 'post-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#post');

            $menu->post->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.post')]).'</span>', ['route' => 'post.index'])
                ->data('permission', 'post-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('post.index')) ? 'nav-link active' : 'nav-link']);

            $menu->post->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.post')]).'</span>', ['route' => 'post.create'])
                ->data('permission', [ 'post-add', 'post-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('post.create') || request()->routeIs('post.edit') ? 'nav-link active' : 'nav-link']);

            $menu->post->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.category')]).'</span>', ['route' => 'category.index'])
                ->data('permission', 'category-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('category.*') ? 'nav-link active' : 'nav-link']);

            $menu->post->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.tags')]).'</span>', ['route' => 'tags.index'])
                ->data('permission', 'tags-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('tags.*') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.package').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="4.75" y="1.75" width="14.5" height="20.5" rx="1.25" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M13.0896 6.50024L12.6588 5.17427C12.4514 4.53607 11.5486 4.53607 11.3412 5.17427L10.9104 6.50024H9.51615C8.84511 6.50024 8.56611 7.35893 9.10899 7.75336L10.2369 8.57286L9.80609 9.89883C9.59873 10.537 10.3292 11.0677 10.8721 10.6733L12 9.8538L13.1279 10.6733C13.6708 11.0677 14.4013 10.537 14.1939 9.89883L13.7631 8.57286L14.891 7.75336C15.4339 7.35893 15.1549 6.50024 14.4838 6.50024H13.0896Z" stroke="currentColor" stroke-width="1.14541"/>
                            <path d="M8 15.0215L16 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M8 19.0215L16 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        </i>')
                ->nickname('package')
                ->data('permission', 'package-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#package');

            $menu->package->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.package')]).'</span>', ['route' => 'package.index'])
                ->data('permission', 'package-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('package.index')) ? 'nav-link active' : 'nav-link']);

            $menu->package->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.package')]).'</span>', ['route' => 'package.create'])
                ->data('permission', [ 'package-add', 'package-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('package.create') || request()->routeIs('package.edit') ? 'nav-link active' : 'nav-link']);


        $menu->add('<span class="item-name">'.__('message.class_schedule').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                            <svg class="icon-24" width="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.9951 16.6766V14.1396" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M18.19 5.33008C19.88 5.33008 21.24 6.70008 21.24 8.39008V11.8301C18.78 13.2701 15.53 14.1401 11.99 14.1401C8.45 14.1401 5.21 13.2701 2.75 11.8301V8.38008C2.75 6.69008 4.12 5.33008 5.81 5.33008H18.19Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M15.4951 5.32576V4.95976C15.4951 3.73976 14.5051 2.74976 13.2851 2.74976H10.7051C9.48512 2.74976 8.49512 3.73976 8.49512 4.95976V5.32576" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M2.77441 15.4829L2.96341 17.9919C3.09141 19.6829 4.50041 20.9899 6.19541 20.9899H17.7944C19.4894 20.9899 20.8984 19.6829 21.0264 17.9919L21.2154 15.4829" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </i>')
                ->nickname('class_schedule')
                ->data('permission', 'class-schedule-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#class_schedule');

            $menu->class_schedule->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.class_schedule')]).'</span>', ['route' => 'classschedule.index'])
                ->data('permission', 'class-schedule-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('classschedule.index')) ? 'nav-link active' : 'nav-link']);

            $menu->class_schedule->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.class_schedule')]).'</span>', ['route' => 'classschedule.create'])
                ->data('permission', [ 'class-schedule-add', 'class-schedule-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('classschedule.create') || request()->routeIs('classschedule.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.subscription').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 20.3884H7.25993C8.27079 20.3884 9.29253 20.4937 10.2763 20.6964C12.0166 21.0549 13.8488 21.0983 15.6069 20.8138C16.4738 20.6734 17.326 20.4589 18.0975 20.0865C18.7939 19.7504 19.6469 19.2766 20.2199 18.7459C20.7921 18.216 21.388 17.3487 21.8109 16.6707C22.1736 16.0894 21.9982 15.3762 21.4245 14.943C20.7873 14.4619 19.8417 14.462 19.2046 14.9433L17.3974 16.3084C16.697 16.8375 15.932 17.3245 15.0206 17.4699C14.911 17.4874 14.7962 17.5033 14.6764 17.5172M14.6764 17.5172C14.6403 17.5214 14.6038 17.5254 14.5668 17.5292M14.6764 17.5172C14.8222 17.486 14.9669 17.396 15.1028 17.2775C15.746 16.7161 15.7866 15.77 15.2285 15.1431C15.0991 14.9977 14.9475 14.8764 14.7791 14.7759C11.9817 13.1074 7.62942 14.3782 5 16.2429M14.6764 17.5172C14.6399 17.525 14.6033 17.5292 14.5668 17.5292M14.5668 17.5292C14.0434 17.5829 13.4312 17.5968 12.7518 17.5326" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <rect x="2" y="14" width="3" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M11.1992 9H14.7992" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18.7654 6.78078L18.9029 5.56316C19.0109 4.60685 19.0649 4.1287 18.8686 3.93104C18.7624 3.82412 18.618 3.7586 18.4636 3.7473C18.1782 3.72641 17.8198 4.06645 17.1029 4.74654C16.7321 5.09825 16.5468 5.2741 16.34 5.30134C16.2254 5.31643 16.1086 5.30091 16.0028 5.25654C15.8119 5.17646 15.6846 4.95906 15.43 4.52426L14.0878 2.23243C13.6067 1.41081 13.3661 1 13 1C12.6339 1 12.3933 1.41081 11.9122 2.23243L10.57 4.52426C10.3154 4.95906 10.1881 5.17646 9.99716 5.25654C9.89135 5.30091 9.77461 5.31643 9.66002 5.30134C9.45323 5.2741 9.26786 5.09825 8.89712 4.74654C8.18025 4.06645 7.82181 3.72641 7.53639 3.7473C7.38199 3.7586 7.23759 3.82412 7.13139 3.93104C6.93508 4.1287 6.98908 4.60685 7.09708 5.56316L7.2346 6.78078C7.46119 8.78708 7.57449 9.79024 8.28406 10.3951C8.99363 11 10.0571 11 12.184 11H13.816C15.9429 11 17.0064 11 17.7159 10.3951C18.4255 9.79024 18.5388 8.78708 18.7654 6.78078Z" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                        </i>')
                ->nickname('subscription')
                ->data('permission', 'subscription-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#subscription');

            $menu->subscription->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.subscription')]).'</span>', ['route' => 'subscription.index'])
                ->data('permission', 'subscription-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('subscription.index')) ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.posting').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                            <svg class="icon-24" width="24" viewBox="0 -960 960 960" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                               <path d="M0-240v-63q0-43 44-70t116-27q13 0 25 .5t23 2.5q-14 21-21 44t-7 48v65H0Zm240 0v-65q0-32 17.5-58.5T307-410q32-20 76.5-30t96.5-10q53 0 97.5 10t76.5 30q32 20 49 46.5t17 58.5v65H240Zm540 0v-65q0-26-6.5-49T754-397q11-2 22.5-2.5t23.5-.5q72 0 116 26.5t44 70.5v63H780ZM160-440q-33 0-56.5-23.5T80-520q0-34 23.5-57t56.5-23q34 0 57 23t23 57q0 33-23 56.5T160-440Zm640 0q-33 0-56.5-23.5T720-520q0-34 23.5-57t56.5-23q34 0 57 23t23 57q0 33-23 56.5T800-440Zm-320-40q-50 0-85-35t-35-85q0-51 35-85.5t85-34.5q51 0 85.5 34.5T600-600q0 50-34.5 85T480-480Z"/>
                            </svg>
                        </i>')
                ->nickname('posting')
                ->data('permission', 'posting-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#posting');

            $menu->posting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.posting')]).'</span>', ['route' => 'posting.index'])
                ->data('permission', 'posting-list')
                ->prepend('<i class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg>
                </i>')
                ->link->attr(['class' => activeRoute(route('posting.index')) || request()->routeIs('posting.show') ? 'nav-link active' : 'nav-link']);

            $menu->posting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.reported_posting')]).'</span>', ['route' => 'posting.reported'])
                ->data('permission', 'reported-posting-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('posting.reported')) ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.ingredient').'</span>', ['class' => ''])
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M640-80q-100 0-170-70t-70-170q0-100 70-170t170-70q100 0 170 70t70 170q0 100-70 170T640-80Zm0-80q66 0 113-47t47-113q0-66-47-113t-113-47q-66 0-113 47t-47 113q0 66 47 113t113 47Zm-480 0q-33 0-56.5-23.5T80-240v-304q0-8 1.5-16t4.5-16l80-184h-6q-17 0-28.5-11.5T120-800v-40q0-17 11.5-28.5T160-880h280q17 0 28.5 11.5T480-840v40q0 17-11.5 28.5T440-760h-6l66 152q-19 10-36 21t-32 25l-84-198h-96l-92 216v304h170q5 21 13.5 41.5T364-160H160Zm480-440q-42 0-71-29t-29-71q0-42 29-71t71-29v200q0-42 29-71t71-29q42 0 71 29t29 71H640Z"/></svg></i>')
            ->nickname('ingredient')
            ->data('permission', ['ingredientcategory-list','ingredientcategory-add','ingredient-list','ingredient-add','measurementunit-list','measurementunit-add','ingredient-unit-conversion-list','ingredient-unit-conversion-add'])
            ->link->attr(['class' => 'nav-link'])
            ->href('#ingredient');

            $menu->ingredient->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.ingredient_category')]).'</span>', ['route' => 'ingredientcategory.index'])
                ->data('permission', 'ingredientcategory-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-120v-120H80v-80h280q33 0 56.5 23.5T440-240v120h-80Zm160 0v-120q0-33 23.5-56.5T600-320h280v80H600v120h-80ZM240-360q-50 0-85-35t-35-85v-160h720v160q0 50-35 85t-85 35H240Zm0-80h480q17 0 28.5-11.5T760-480v-80H200v80q0 17 11.5 28.5T240-440ZM120-680v-80h240v-40q0-17 11.5-28.5T400-840h160q17 0 28.5 11.5T600-800v40h240v80H120Zm80 240v-120 120Z"/></svg>                    </i>')
                ->link->attr(['class' => activeRoute(route('ingredientcategory.index')) ? 'nav-link active' : 'nav-link']);

            $menu->ingredient->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.ingredient_category')]).'</span>', ['route' => 'ingredientcategory.create'])
                ->data('permission', ['ingredientcategory-add','ingredientcategory-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M120-640h720v360q0 50-35 85t-85 35H240q-50 0-85-35t-35-85v-360Zm80 80v280q0 17 11.5 28.5T240-240h480q17 0 28.5-11.5T760-280v-280H200Zm-80-120v-80h240v-40q0-17 11.5-28.5T400-840h160q17 0 28.5 11.5T600-800v40h240v80H120Zm360 280Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('ingredientcategory.create')) || request()->routeIs('ingredientcategory.edit') ? 'nav-link active' : 'nav-link']);

            $menu->ingredient->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.ingredient')]).'</span>', ['route' => 'ingredient.index'])
                ->data('permission', 'ingredient-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 9H16M8 13H16" stroke="currentColor" stroke-width="2"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('ingredient.index')) ? 'nav-link active' : 'nav-link']);

            $menu->ingredient->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.ingredient')]).'</span>', ['route' => 'ingredient.create'])
                ->data('permission', ['ingredient-add','ingredient-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"><rect x="4" y="5" width="16" height="14" rx="2" stroke="currentColor" stroke-width="2"/><path d="M12 9V15M9 12H15" stroke="currentColor" stroke-width="2"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('ingredient.create')) || request()->routeIs('ingredient.edit') ? 'nav-link active' : 'nav-link']);

            $menu->ingredient->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.measurement_unit')]).'</span>', ['route' => 'measurementunit.index'])
                ->data('permission', 'measurementunit-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M80-120v-80h360v-447q-26-9-45-28t-28-45H240l120 280q0 50-41 85t-99 35q-58 0-99-35t-41-85l120-280h-80v-80h247q12-35 43-57.5t70-22.5q39 0 70 22.5t43 57.5h247v80h-80l120 280q0 50-41 85t-99 35q-58 0-99-35t-41-85l120-280H593q-9 26-28 45t-45 28v447h360v80H80Zm585-320h150l-75-174-75 174Zm-520 0h150l-75-174-75 174Zm335-280q17 0 28.5-11.5T520-760q0-17-11.5-28.5T480-800q-17 0-28.5 11.5T440-760q0 17 11.5 28.5T480-720Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('measurementunit.index')) ? 'nav-link active' : 'nav-link']);

            $menu->ingredient->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.measurement_unit')]).'</span>', ['route' => 'measurementunit.create'])
                ->data('permission', ['measurementunit-add', 'measurementunit-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M80-80q0-111 29.5-189.5T185-400q46-52 103-80.5T400-520v-120q-137-17-228.5-84.5T80-880h800q0 88-91.5 155.5T560-640v120q55 11 112 39.5T775-400q46 52 75.5 130.5T880-80H640v-80h155q-18-152-113.5-220T480-448q-106 0-201.5 68T165-160h155v80H80Zm562-659.5Q713-764 755-800H205q42 36 113 60.5T480-715q91 0 162-24.5ZM480-80q-33 0-56.5-23.5T400-160q0-17 6.5-31t17.5-25q24-24 81-50.5T640-320q-28 78-54 135t-50 81q-11 11-25 17.5T480-80Zm0-635Z"/></svg>                    </i>')
                ->link->attr(['class' => request()->routeIs('measurementunit.create') || request()->routeIs('measurementunit.edit') ? 'nav-link active' : 'nav-link']);
            
            $menu->ingredient->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.ingredient_unit_conversion')]).'</span>', ['route' => 'ingredient-unit-conversion.index'])
                ->data('permission', 'ingredient-unit-conversion-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 -960 960 960" fill="currentColor"><path d="M280-120 80-320l200-200 57 56-104 104h607v80H233l104 104-57 56Zm400-320-57-56 104-104H120v-80h607L623-784l57-56 200 200-200 200Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('ingredient-unit-conversion.index')) ? 'nav-link active' : 'nav-link']);

            $menu->ingredient->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.ingredient_unit_conversion')]).'</span>', ['route' => 'ingredient-unit-conversion.create'])
                ->data('permission', ['ingredient-unit-conversion-add','ingredient-unit-conversion-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"><path d="M7 7H17L14 4M17 17H7L10 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 10V14M10 12H14" stroke="currentColor" stroke-width="2"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('ingredient-unit-conversion.create')) || request()->routeIs('ingredient-unit-conversion.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.recipe').'</span>', ['class' => ''])
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-400h80v-200h-80v200Zm-160-60q-46-23-73-66.5T100-621q0-75 51.5-127T278-800q12 0 24.5 2t24.5 5q25-41 65-64t88-23q48 0 88 23t65 64q12-3 24-5t25-2q75 0 126.5 52T860-621q0 51-27 94.5T760-460v220H200v-220Zm320 60h80v-200h-80v200Zm-240 80h400v-189l44-22q26-13 41-36.5t15-52.5q0-42-28.5-71T682-720q-11 0-20 2t-19 5l-47 13-31-52q-14-23-36.5-35.5T480-800q-26 0-48.5 12.5T395-752l-31 52-48-13q-10-2-19.5-4.5T277-720q-41 0-69 29t-28 71q0 29 15 52.5t41 36.5l44 22v189Zm-80 80h80v80h400v-80h80v160H200v-160Zm280-80Z"/></svg></i>')
            ->nickname('recipe')
            ->data('permission', ['recipe-list','recipe-add','recipe-category-list','recipe-category-add','recipe-tag-list','recipe-tag-add'])
            ->link->attr(['class' => 'nav-link'])
            ->href('#recipe');
           
            $menu->recipe->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.category')]).'</span>', ['route' => 'recipe-category.index'])
                ->data('permission', 'recipe-category-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M320-760v-40q0-33 23.5-56.5T400-880h160q33 0 56.5 23.5T640-800v40h120q33 0 56.5 23.5T840-680v480q0 33-23.5 56.5T760-120H200q-33 0-56.5-23.5T120-200v-480q0-33 23.5-56.5T200-760h120ZM200-200h560v-360h-80v80q0 33-23.5 56.5T600-400H360q-33 0-56.5-23.5T280-480v-80h-80v360Zm91.5-51.5Q280-263 280-280t11.5-28.5Q303-320 320-320t28.5 11.5Q360-297 360-280t-11.5 28.5Q337-240 320-240t-28.5-11.5Zm160 0Q440-263 440-280t11.5-28.5Q463-320 480-320t28.5 11.5Q520-297 520-280t-11.5 28.5Q497-240 480-240t-28.5-11.5Zm160 0Q600-263 600-280t11.5-28.5Q623-320 640-320t28.5 11.5Q680-297 680-280t-11.5 28.5Q657-240 640-240t-28.5-11.5ZM360-480h240v-80H360v80ZM200-640h560v-40H200v40Zm200-120h160v-40H400v40Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('recipe-category.index')) ? 'nav-link active' : 'nav-link']);
           
            $menu->recipe->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.category')]).'</span>', ['route' => 'recipe-category.create'])
                ->data('permission', ['recipe-category-add','recipe-category-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M120-640h720v360q0 50-35 85t-85 35H240q-50 0-85-35t-35-85v-360Zm80 80v280q0 17 11.5 28.5T240-240h480q17 0 28.5-11.5T760-280v-280H200Zm-80-120v-80h240v-40q0-17 11.5-28.5T400-840h160q17 0 28.5 11.5T600-800v40h240v80H120Zm360 280Z"/></svg>                    </i>')
                ->link->attr(['class' => activeRoute(route('recipe-category.create')) || request()->routeIs('recipe-category.edit') ? 'nav-link active' : 'nav-link']);
 
            $menu->recipe->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.tags')]).'</span>', ['route' => 'recipe-tag.index'])
                ->data('permission', 'recipe-tag-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M560-160v-23q0-24 13-44t36-30q28-11 54.5-17t59.5-6q29 0 57 6t51 17q23 10 36 30t13 44v23H560Zm160-160q-33 0-56.5-23.5T640-400q0-33 23.5-56.5T720-480q33 0 56.5 23.5T800-400q0 33-23.5 56.5T720-320ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v226q-19-9-39-14.5t-41-8.5v-203H200v360h168q9 27 30 47t47 28q-3 20-4 40.5t2 40.5q-36-7-67.5-26.5T320-320H200v120h253q7 22 16 42t22 38H200Zm0-80h253-253Zm80-410h400v-80H280v80Zm0 140h237q27-29 60.5-49t72.5-31H280v80Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('recipe-tag.index')) ? 'nav-link active' : 'nav-link']);

            $menu->recipe->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.tags')]).'</span>', ['route' => 'recipe-tag.create'])
                ->data('permission', ['recipe-tag-add','recipe-tag-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M480-160v-80h120l180-240-180-240H160v200H80v-200q0-33 23.5-56.5T160-800h440q19 0 36 8.5t28 23.5l216 288-216 288q-11 15-28 23.5t-36 8.5H480Zm-10-320ZM200-120v-120H80v-80h120v-120h80v120h120v80H280v120h-80Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('recipe-tag.create')) || request()->routeIs('recipe-tag.edit') ? 'nav-link active' : 'nav-link']);

            $menu->recipe->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.recipe')]).'</span>', ['route' => 'recipe.index'])
                ->data('permission', 'recipe-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M177-560q14-36 4.5-64T149-680q-33-40-43.5-75.5T102-840h78q-8 38-2.5 62t28.5 52q38 46 48.5 81.5t.5 84.5h-78Zm160 0q14-36 5-64t-32-56q-33-40-44-75.5t-4-84.5h78q-8 38-2.5 62t28.5 52q38 46 48.5 81.5t.5 84.5h-78Zm160 0q14-36 5-64t-32-56q-33-40-44-75.5t-4-84.5h78q-8 38-2.5 62t28.5 52q38 46 48.5 81.5t.5 84.5h-78ZM200-160q-50 0-85-35t-35-85v-200h561q5-34 27-59.5t54-36.5l185-62 25 76-185 62q-12 4-19.5 14.5T720-462v182q0 50-35 85t-85 35H200Zm0-80h400q17 0 28.5-11.5T640-280v-120H160v120q0 17 11.5 28.5T200-240Zm200-80Z"/></svg>                    </i>')
                ->link->attr(['class' => activeRoute(route('recipe.index')) ? 'nav-link active' : 'nav-link']);

            $menu->recipe->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.recipe')]).'</span>', ['route' => 'recipe.create'])
                ->data('permission', ['recipe-add','recipe-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M240-360q-50 0-85-35t-35-85v-160h521q5-33 27-59t54-37l185-62 25 76-185 62q-12 4-19.5 14.5T720-622v142q0 50-35 85t-85 35H240Zm0-80h360q17 0 28.5-11.5T640-480v-80H200v80q0 17 11.5 28.5T240-440Zm120 320v-120H80v-80h280q33 0 56.5 23.5T440-240v120h-80Zm160 0v-120q0-33 23.5-56.5T600-320h280v80H600v120h-80ZM420-500Z"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('recipe.create')) || request()->routeIs('recipe.edit') ? 'nav-link active' : 'nav-link']);
    
        $menu->add('<span class="item-name">'.__('message.bannerslider').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="6" y="7" width="12" height="4" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M6 15H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M6 18H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg></i>')
            ->nickname('bannerslider')
            ->data('permission', 'bannerslider-list')
            ->link->attr(['class' => 'nav-link'])
            ->href('#bannerslider');

            $menu->bannerslider->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.bannerslider')]).'</span>', ['route' => 'bannerslider.index'])
                ->data('permission', 'bannerslider-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg></i>')
                ->link->attr(['class' => activeRoute(route('bannerslider.index')) ? 'nav-link active' : 'nav-link']);

            $menu->bannerslider->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.bannerslider')]).'</span>', ['route' => 'bannerslider.create'])
                ->data('permission', ['bannerslider-add', 'bannerslider-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg></i>')
                ->link->attr(['class' => request()->routeIs('bannerslider.create') || request()->routeIs('bannerslider.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span>'.__('message.account_setting').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="6" r="4" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M14 20.8344C13.3663 20.9421 12.695 21 12 21C8.13401 21 5 19.2091 5 17C5 14.7909 8.13401 13 12 13C13.7135 13 15.2832 13.3518 16.5 13.9359" stroke="currentColor" stroke-width="1.5"/>
                    <circle cx="17" cy="18" r="1.25" stroke="currentColor"/>
                        <path d="M17.7343 13.8969C17.5812 13.8335 17.387 13.8335 16.9987 13.8335C16.6104 13.8335 16.4163 13.8335 16.2632 13.8969C16.059 13.9815 15.8967 14.1437 15.8122 14.3479C15.7736 14.4411 15.7584 14.5495 15.7525 14.7077C15.7438 14.94 15.6247 15.1551 15.4233 15.2714C15.2219 15.3876 14.9761 15.3833 14.7705 15.2746C14.6306 15.2007 14.5292 15.1596 14.4291 15.1464C14.21 15.1176 13.9884 15.177 13.8131 15.3115C13.6815 15.4124 13.5845 15.5805 13.3903 15.9168C13.1962 16.2531 13.0991 16.4212 13.0775 16.5855C13.0486 16.8047 13.108 17.0263 13.2426 17.2016C13.304 17.2816 13.3903 17.3489 13.5242 17.4331C13.7212 17.5568 13.8479 17.7676 13.8478 18.0002C13.8478 18.2327 13.7211 18.4435 13.5242 18.5672C13.3903 18.6514 13.3039 18.7186 13.2425 18.7987C13.108 18.974 13.0486 19.1956 13.0774 19.4148C13.0991 19.5791 13.1962 19.7472 13.3903 20.0835C13.5844 20.4198 13.6815 20.5879 13.813 20.6888C13.9884 20.8233 14.21 20.8827 14.4291 20.8539C14.5291 20.8407 14.6305 20.7996 14.7704 20.7257C14.976 20.617 15.2219 20.6127 15.4233 20.7289C15.6247 20.8452 15.7438 21.0603 15.7525 21.2927C15.7584 21.4508 15.7736 21.5592 15.8122 21.6524C15.8967 21.8566 16.059 22.0188 16.2632 22.1034C16.4163 22.1668 16.6104 22.1668 16.9987 22.1668C17.387 22.1668 17.5812 22.1668 17.7343 22.1034C17.9385 22.0188 18.1007 21.8566 18.1853 21.6524C18.2239 21.5592 18.239 21.4508 18.2449 21.2927C18.2536 21.0603 18.3728 20.8452 18.5741 20.7289C18.7755 20.6126 19.0214 20.617 19.227 20.7256C19.3669 20.7996 19.4683 20.8407 19.5683 20.8538C19.7875 20.8827 20.0091 20.8233 20.1844 20.6888C20.3159 20.5879 20.413 20.4197 20.6071 20.0835C20.8013 19.7472 20.8983 19.5791 20.92 19.4147C20.9488 19.1956 20.8894 18.974 20.7549 18.7987C20.6935 18.7186 20.6072 18.6513 20.4732 18.5672C20.2763 18.4434 20.1496 18.2327 20.1496 18.0001C20.1496 17.7676 20.2763 17.5569 20.4732 17.4332C20.6072 17.349 20.6935 17.2817 20.7549 17.2016C20.8895 17.0263 20.9489 16.8047 20.92 16.5856C20.8984 16.4212 20.8013 16.2531 20.6072 15.9168C20.413 15.5806 20.316 15.4124 20.1845 15.3115C20.0091 15.177 19.7875 15.1176 19.5684 15.1464C19.4684 15.1596 19.3669 15.2007 19.227 15.2747C19.0214 15.3833 18.7756 15.3877 18.5742 15.2714C18.3728 15.1551 18.2536 14.94 18.2449 14.7076C18.239 14.5495 18.2239 14.4411 18.1853 14.3479C18.1007 14.1437 17.9385 13.9815 17.7343 13.8969Z" stroke="currentColor"/>
                </svg></i>')
            ->nickname('account_setting')
            ->data('permission', ['role-list','permission-list'])
            ->link->attr(['class' => 'nav-link' ])
            ->href('#account_setting');

            $menu->account_setting->add('<span>'.__('message.list_form_title',['form' => __('message.role')]).'</span>', ['route' => 'role.index'])
                ->data('permission', 'role-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('role.index')) ? 'nav-link active' : 'nav-link']);

            $menu->account_setting->add('<span>'.__('message.list_form_title',['form' => __('message.permission')]).'</span>', ['route' => 'permission.index'])
                ->data('permission', 'permission-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('permission.index')) ? 'nav-link active' : 'nav-link']);

        $menu->add('<span>'.__('message.pages').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20.3116 12.6473L20.8293 10.7154C21.4335 8.46034 21.7356 7.3328 21.5081 6.35703C21.3285 5.58657 20.9244 4.88668 20.347 4.34587C19.6157 3.66095 18.4881 3.35883 16.2331 2.75458C13.978 2.15033 12.8504 1.84821 11.8747 2.07573C11.1042 2.25537 10.4043 2.65945 9.86351 3.23687C9.27709 3.86298 8.97128 4.77957 8.51621 6.44561C8.43979 6.7254 8.35915 7.02633 8.27227 7.35057L8.27222 7.35077L7.75458 9.28263C7.15033 11.5377 6.84821 12.6652 7.07573 13.641C7.25537 14.4115 7.65945 15.1114 8.23687 15.6522C8.96815 16.3371 10.0957 16.6392 12.3508 17.2435L12.3508 17.2435C14.3834 17.7881 15.4999 18.0873 16.415 17.9744C16.5152 17.9621 16.6129 17.9448 16.7092 17.9223C17.4796 17.7427 18.1795 17.3386 18.7203 16.7612C19.4052 16.0299 19.7074 14.9024 20.3116 12.6473Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M16.415 17.9741C16.2065 18.6126 15.8399 19.1902 15.347 19.6519C14.6157 20.3368 13.4881 20.6389 11.2331 21.2432C8.97798 21.8474 7.85044 22.1495 6.87466 21.922C6.10421 21.7424 5.40432 21.3383 4.86351 20.7609C4.17859 20.0296 3.87647 18.9021 3.27222 16.647L2.75458 14.7151C2.15033 12.46 1.84821 11.3325 2.07573 10.3567C2.25537 9.58627 2.65945 8.88638 3.23687 8.34557C3.96815 7.66065 5.09569 7.35853 7.35077 6.75428C7.77741 6.63996 8.16368 6.53646 8.51621 6.44531" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M11.7773 10L16.607 11.2941" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M11 12.8975L13.8978 13.6739" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg></i>')
            ->nickname('pages')
            ->data('permission', ['terms-condition','privacy-policy'])
            ->link->attr(['class' => 'nav-link' ])
            ->href('#pages');

            if(Module::has('Frontend') && Module::isEnabled('Frontend')) {
                $menu->pages->add('<span>'.__('frontend::message.list').'</span>', ['route' => 'pages.index'])
                    ->data('permission', 'page-list')
                    ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                    ->link->attr(['class' => activeRoute(route('pages.index')) ? 'nav-link active' : 'nav-link']);
            }

            $menu->pages->add('<span>'.__('message.terms_condition').'</span>', ['route' => 'pages.term_condition'])
                ->data('permission', 'terms-condition')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('pages.term_condition')) ? 'nav-link active' : 'nav-link']);

            $menu->pages->add('<span>'.__('message.privacy_policy').'</span>', ['route' => 'pages.privacy_policy'])
                ->data('permission', 'privacy-policy')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('pages.privacy_policy')) ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.pushnotification').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18.7491 9.70957V9.00497C18.7491 5.13623 15.7274 2 12 2C8.27256 2 5.25087 5.13623 5.25087 9.00497V9.70957C5.25087 10.5552 5.00972 11.3818 4.5578 12.0854L3.45036 13.8095C2.43882 15.3843 3.21105 17.5249 4.97036 18.0229C9.57274 19.3257 14.4273 19.3257 19.0296 18.0229C20.789 17.5249 21.5612 15.3843 20.5496 13.8095L19.4422 12.0854C18.9903 11.3818 18.7491 10.5552 18.7491 9.70957Z" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M7.5 19C8.15503 20.7478 9.92246 22 12 22C14.0775 22 15.845 20.7478 16.5 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg></i>')
            ->nickname('pushnotification')
            ->data('permission', 'pushnotification-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#pushnotification');

            $menu->pushnotification->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.pushnotification')]).'</span>', ['route' => 'pushnotification.index'])
                ->data('permission', 'pushnotification-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('pushnotification.index')) ? 'nav-link active' : 'nav-link']);

            $menu->pushnotification->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.pushnotification')]).'</span>', ['route' => 'pushnotification.create'])
                ->data('permission', 'pushnotification-add')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('pushnotification.create') || request()->routeIs('resend.pushnotification') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.quotes').'</span>', ['class' => ''])
                ->prepend('<i class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.0894 14.3092L6.58097 14.4006C6.60635 14.2642 6.5739 14.1234 6.49139 14.0118C6.40888 13.9003 6.28372 13.828 6.14586 13.8124L6.0894 14.3092ZM6.21523 5.9626L6.18824 6.46188L6.20173 6.4626H6.21523V5.9626ZM9.75938 13.701L10.229 13.8726L9.75938 13.701ZM3.38411 19.0277L2.9762 18.7386C2.8602 18.9022 2.85322 19.1193 2.95847 19.2901C3.06372 19.4608 3.26083 19.5522 3.45914 19.5221L3.38411 19.0277ZM6.14586 13.8124C5.30247 13.7165 4.37659 13.38 3.67228 12.7945C2.98015 12.2191 2.5 11.4052 2.5 10.3037H1.5C1.5 11.7387 2.14181 12.8226 3.03302 13.5635C3.91204 14.2942 5.03086 14.6921 6.03295 14.806L6.14586 13.8124ZM2.5 10.3037C2.5 8.01378 4.18533 6.35361 6.18824 6.46188L6.24222 5.46333C3.58951 5.31994 1.5 7.52057 1.5 10.3037H2.5ZM6.21523 6.4626C8.40815 6.4626 9.84658 8.47295 9.84658 10.3037H10.8466C10.8466 8.06594 9.09529 5.4626 6.21523 5.4626V6.4626ZM9.84658 10.3037C9.84658 11.3473 9.76326 12.2337 9.28976 13.5294L10.229 13.8726C10.7518 12.4421 10.8466 11.4347 10.8466 10.3037H9.84658ZM9.28976 13.5294C9.11014 14.0209 7.7045 17.8662 3.30907 18.5334L3.45914 19.5221C8.45885 18.7632 10.0408 14.3878 10.229 13.8726L9.28976 13.5294ZM3.79202 19.3169C4.34794 18.5326 4.98077 17.7465 5.47004 16.9968C5.9777 16.2189 6.39675 15.3908 6.58097 14.4006L5.59784 14.2177C5.44652 15.0311 5.10012 15.7339 4.63261 16.4502C4.14671 17.1947 3.59466 17.8661 2.9762 18.7386L3.79202 19.3169Z" stroke="currentColor" stroke-width="1"/>
                            <path d="M16.7432 14.2938L17.2348 14.3853C17.2602 14.2488 17.2277 14.108 17.1452 13.9965C17.0627 13.8849 16.9375 13.8127 16.7997 13.797L16.7432 14.2938ZM16.869 5.94722L16.8421 6.44649L16.8555 6.44722H16.869V5.94722ZM20.4132 13.6856L20.8828 13.8573L20.4132 13.6856ZM14.0379 19.0123L13.63 18.7232C13.514 18.8868 13.507 19.104 13.6123 19.2747C13.7175 19.4455 13.9146 19.5368 14.1129 19.5067L14.0379 19.0123ZM16.7997 13.797C15.9563 13.7012 15.0304 13.3646 14.3261 12.7791C13.634 12.2037 13.1538 11.3898 13.1538 10.2883H12.1538C12.1538 11.7233 12.7956 12.8072 13.6868 13.5481C14.5659 14.2788 15.6847 14.6767 16.6868 14.7906L16.7997 13.797ZM13.1538 10.2883C13.1538 7.9984 14.8391 6.33823 16.8421 6.44649L16.896 5.44795C14.2433 5.30456 12.1538 7.50519 12.1538 10.2883H13.1538ZM16.869 6.44722C19.062 6.44722 20.5004 8.45757 20.5004 10.2883H21.5004C21.5004 8.05056 19.7491 5.44722 16.869 5.44722V6.44722ZM20.5004 10.2883C20.5004 11.3319 20.4171 12.2183 19.9436 13.514L20.8828 13.8573C21.4056 12.4267 21.5004 11.4193 21.5004 10.2883H20.5004ZM19.9436 13.514C19.764 14.0055 18.3583 17.8508 13.9629 18.518L14.1129 19.5067C19.1127 18.7478 20.6946 14.3724 20.8828 13.8573L19.9436 13.514ZM14.4458 19.3015C15.0018 18.5172 15.6346 17.7311 16.1239 16.9814C16.6315 16.2035 17.0506 15.3754 17.2348 14.3853L16.2516 14.2023C16.1003 15.0157 15.7539 15.7185 15.2864 16.4349C14.8005 17.1794 14.2485 17.8507 13.63 18.7232L14.4458 19.3015Z" stroke="currentColor" stroke-width="1"/>
                        </svg></i>')
                ->nickname('quotes')
                ->data('permission', 'quotes-list')
                ->link->attr(['class' => 'nav-link' ])
                ->href('#quotes');

            $menu->quotes->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.quotes')]).'</span>', ['route' => 'quotes.index'])
                ->data('permission', 'quotes-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('quotes.index')) ? 'nav-link active' : 'nav-link']);

            $menu->quotes->add('<span class="item-name">'.__('message.add_form_title',['form' => __('message.quotes')]).'</span>', ['route' => 'quotes.create'])
                ->data('permission', [ 'quotes-add', 'quotes-edit'])
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('quotes.create') || request()->routeIs('quotes.edit') ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.app_language_setting').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg class="icon-32" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.8877 10.8967C19.2827 10.7007 20.3567 9.50473 20.3597 8.05573C20.3597 6.62773 19.3187 5.44373 17.9537 5.21973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M19.7285 14.2505C21.0795 14.4525 22.0225 14.9255 22.0225 15.9005C22.0225 16.5715 21.5785 17.0075 20.8605 17.2815" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.8867 14.6638C8.67273 14.6638 5.92773 15.1508 5.92773 17.0958C5.92773 19.0398 8.65573 19.5408 11.8867 19.5408C15.1007 19.5408 17.8447 19.0588 17.8447 17.1128C17.8447 15.1668 15.1177 14.6638 11.8867 14.6638Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M11.8869 11.888C13.9959 11.888 15.7059 10.179 15.7059 8.069C15.7059 5.96 13.9959 4.25 11.8869 4.25C9.7779 4.25 8.0679 5.96 8.0679 8.069C8.0599 10.171 9.7569 11.881 11.8589 11.888H11.8869Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M5.88509 10.8967C4.48909 10.7007 3.41609 9.50473 3.41309 8.05573C3.41309 6.62773 4.45409 5.44373 5.81909 5.21973" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M4.044 14.2505C2.693 14.4525 1.75 14.9255 1.75 15.9005C1.75 16.5715 2.194 17.0075 2.912 17.2815" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg></i>')
            ->nickname('app_language_setting')
            ->data('permission', ['screen-list', 'defaultkeyword-list', 'languagelist-list', 'languagewithkeyword-list', 'bulkimport-add'])
            ->link->attr(['class' => 'nav-link' ])
            ->href('#app_language_setting');

            $menu->app_language_setting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.screen')]).'</span>', ['route' => 'screen.index'])
                ->data('permission', 'screen-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('screen.index')) ? 'nav-link active' : 'nav-link']);

            $menu->app_language_setting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.default_keyword')]).'</span>', ['route' => 'defaultkeyword.index'])
                ->data('permission', 'defaultkeyword-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('defaultkeyword.index')) ? 'nav-link active' : 'nav-link']);

            $menu->app_language_setting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.language')]).'</span>', ['route' => 'languagelist.index'])
                ->data('permission', 'languagelist-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => request()->routeIs('languagelist.index') || request()->routeIs('languagelist.create') || request()->routeIs('languagelist.edit') ? 'nav-link active' : 'nav-link']);

            $menu->app_language_setting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.language_keyword')]).'</span>', ['route' => 'languagewithkeyword.index'])
                ->data('permission', 'languagewithkeyword-list')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('languagewithkeyword.index')) ? 'nav-link active' : 'nav-link']);

            $menu->app_language_setting->add('<span class="item-name">'.__('message.list_form_title',['form' => __('message.bulk_import_langugage_data')]).'</span>', ['route' => 'bulk.language.data'])
                ->data('permission', 'bulkimport-add')
                ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
                ->link->attr(['class' => activeRoute(route('bulk.language.data')) ? 'nav-link active' : 'nav-link']);

        $menu->add('<span class="item-name">'.__('message.setting').'</span>', ['route' => 'setting.index'])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M13.7639 2.15224C13.3963 2 12.9304 2 11.9985 2C11.0666 2 10.6007 2 10.2332 2.15224C9.7431 2.35523 9.35375 2.74458 9.15076 3.23463C9.0581 3.45834 9.02184 3.7185 9.00765 4.09799C8.98679 4.65568 8.70079 5.17189 8.21748 5.45093C7.73417 5.72996 7.14412 5.71954 6.65073 5.45876C6.31498 5.2813 6.07154 5.18262 5.83147 5.15102C5.30558 5.08178 4.77372 5.22429 4.3529 5.5472C4.03728 5.78938 3.80431 6.1929 3.33837 6.99993C2.87243 7.80697 2.63946 8.21048 2.58753 8.60491C2.51829 9.1308 2.6608 9.66266 2.98371 10.0835C3.1311 10.2756 3.33824 10.437 3.65972 10.639C4.13233 10.936 4.43643 11.4419 4.43639 12C4.43636 12.5581 4.13228 13.0639 3.65972 13.3608C3.33818 13.5629 3.13101 13.7244 2.98361 13.9165C2.66071 14.3373 2.5182 14.8691 2.58743 15.395C2.63936 15.7894 2.87233 16.193 3.33827 17C3.80421 17.807 4.03718 18.2106 4.3528 18.4527C4.77362 18.7756 5.30548 18.9181 5.83137 18.8489C6.07143 18.8173 6.31486 18.7186 6.65057 18.5412C7.14401 18.2804 7.73409 18.27 8.21743 18.549C8.70077 18.8281 8.98679 19.3443 9.00765 19.9021C9.02184 20.2815 9.05811 20.5417 9.15076 20.7654C9.35375 21.2554 9.7431 21.6448 10.2332 21.8478C10.6007 22 11.0666 22 11.9985 22C12.9304 22 13.3963 22 13.7639 21.8478C14.2539 21.6448 14.6433 21.2554 14.8463 20.7654C14.9389 20.5417 14.9752 20.2815 14.9894 19.902C15.0103 19.3443 15.2962 18.8281 15.7795 18.549C16.2628 18.2699 16.853 18.2804 17.3464 18.5412C17.6821 18.7186 17.9255 18.8172 18.1656 18.8488C18.6915 18.9181 19.2233 18.7756 19.6442 18.4527C19.9598 18.2105 20.1927 17.807 20.6587 16.9999C21.1246 16.1929 21.3576 15.7894 21.4095 15.395C21.4788 14.8691 21.3362 14.3372 21.0133 13.9164C20.8659 13.7243 20.6588 13.5628 20.3373 13.3608C19.8647 13.0639 19.5606 12.558 19.5607 11.9999C19.5607 11.4418 19.8647 10.9361 20.3373 10.6392C20.6588 10.4371 20.866 10.2757 21.0134 10.0835C21.3363 9.66273 21.4789 9.13087 21.4096 8.60497C21.3577 8.21055 21.1247 7.80703 20.6588 7C20.1928 6.19297 19.9599 5.78945 19.6442 5.54727C19.2234 5.22436 18.6916 5.08185 18.1657 5.15109C17.9256 5.18269 17.6822 5.28136 17.3465 5.4588C16.853 5.71959 16.263 5.73002 15.7796 5.45096C15.2963 5.17191 15.0103 4.65566 14.9894 4.09794C14.9752 3.71848 14.9389 3.45833 14.8463 3.23463C14.6433 2.74458 14.2539 2.35523 13.7639 2.15224Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg></i>')
            ->link->attr([ 'class' => request()->routeIs('setting.index') ? 'nav-link active' : 'nav-link' ]);

        if (auth()->user() && auth()->user()->hasRole('admin')) {
            $menu->add('<span>'.__('message.admin_device_login').'</span>', ['route' => 'admin-login-device.index'])
                ->prepend('<i class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-toggle-on" viewBox="0 0 16 16">
                            <path d="M5 3a5 5 0 0 0 0 10h6a5 5 0 0 0 0-10zm6 9a4 4 0 1 1 0-8 4 4 0 0 1 0 8"/>
                        </svg></i>')
                ->nickname('admin_device_login')
            ->link->attr(['class' => activeRoute(route('admin-login-device.index')) || request()->routeIs('admin-login-device.index') ? 'nav-link active' : 'nav-link']);
        }

    if(Module::has('Frontend') && Module::isEnabled('Frontend')){
        $menu->add('<span class="item-name">'.__('frontend::message.website_section').'</span>', ['class' => ''])
            ->prepend('<i class="icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14.5 15.75H9.5V20.25H14.5V15.75Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M20.5 3.75H3.5C2.94772 3.75 2.5 4.19772 2.5 4.75V14.75C2.5 15.3023 2.94772 15.75 3.5 15.75H20.5C21.0523 15.75 21.5 15.3023 21.5 14.75V4.75C21.5 4.19772 21.0523 3.75 20.5 3.75Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M11 13.25H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 20.25H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg></i>')
            ->nickname('website_section')
            ->data('permission', 'website-section-list')
            ->link->attr(['class' => 'nav-link' ])
            ->href('#website_section');

            $menu->website_section->add('<span class="item-name">'.__('message.information').'</span>', ['route' => ['frontend.website.form', 'app-info']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'app-info')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.ultimate_workout').'</span>', ['route' => ['frontend.website.form', 'ultimate-workout']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'ultimate-workout')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.nutrition').'</span>', ['route' => ['frontend.website.form', 'nutrition']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'nutrition')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.fitness_product').'</span>', ['route' => ['frontend.website.form', 'fitness-product']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'fitness-product')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.fitness_blog').'</span>', ['route' => ['frontend.website.form', 'fitness-blog']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'fitness-blog')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.download_app').'</span>', ['route' => ['frontend.website.form', 'download-app']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'download-app')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.client_testimonial').'</span>', ['route' => ['frontend.website.form', 'client-testimonial']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'client-testimonial')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.newsletter').'</span>', ['route' => ['frontend.website.form', 'newsletter']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'newsletter')) ? 'nav-link active' : 'nav-link']);

            $menu->website_section->add('<span class="item-name">'.__('frontend::message.walkthrough').'</span>', ['route' => ['frontend.website.form', 'walkthrough']])
            ->data('permission', 'website-section-list')
            ->prepend('<i class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="10" viewBox="0 0 24 24" fill="currentColor"><g><circle cx="12" cy="12" r="8" fill="currentColor"></circle></g></svg></i>')
            ->link->attr(['class' => activeRoute(route('frontend.website.form', 'walkthrough')) ? 'nav-link active' : 'nav-link']);
        }

    })->filter(function ($item) {
        return checkMenuRoleAndPermission($item);
    });
@endphp
<ul class="navbar-nav iq-main-menu"  id="sidebar">


    <li><hr class="hr-horizontal"></li>

    @include(config('laravel-menu.views.bootstrap-items'), ['items' => $MyNavBar->roots()])
</ul>

@push('scripts')
<script>
    $(window).on('load', function () {
        setTimeout(function() {
            var $sidebarBody = $('.sidebar-body');
            if ($sidebarBody.length === 0) return;

            var $activeLink = $sidebarBody.find('.nav-link.active');
            if ($activeLink.length === 0) {
                $activeLink = $sidebarBody.find('.active');
            }
            if ($activeLink.length === 0) return;

            var sidebarBodyEl = $sidebarBody.get(0);
            var activeLinkEl = $activeLink.get(0);
            var scrollbar = (window.Scrollbar && window.Scrollbar.get(sidebarBodyEl)) ? window.Scrollbar.get(sidebarBodyEl) : null;
            
            var sidebarRect = sidebarBodyEl.getBoundingClientRect();
            var linkRect = activeLinkEl.getBoundingClientRect();

            var currentScrollTop = scrollbar ? scrollbar.offset.y : $sidebarBody.scrollTop();
            var offset = linkRect.top - sidebarRect.top;
            var target = currentScrollTop + offset - ($sidebarBody.innerHeight() / 2) + ($activeLink.outerHeight() / 2);

            if (scrollbar) {
                scrollbar.scrollTo(0, Math.max(0, target), 1000);
            } else {
                $sidebarBody.stop().animate({ scrollTop: Math.max(0, target) }, 1000);
            }
        }, 500);
    });
</script>
@endpush

