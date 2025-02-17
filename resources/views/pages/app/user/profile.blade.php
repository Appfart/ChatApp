<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title }}
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!--  BEGIN CUSTOM STYLE FILE  -->
        @vite(['resources/scss/light/assets/users/user-profile.scss'])
        @vite(['resources/scss/dark/assets/users/user-profile.scss'])
        <!--  END CUSTOM STYLE FILE  -->
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <!-- BREADCRUMB -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">Profile</li>
            </ol>
        </nav>
    </div>
    <!-- /BREADCRUMB -->

    <div class="row layout-spacing">
        <!-- Content -->
        <div class="col-xl-5 col-lg-12 col-md-12 col-sm-12 layout-top-spacing">
            <div class="user-profile">
                <div class="widget-content widget-content-area">
                    <div class="d-flex justify-content-between">
                        <h3 class="">{{ $user->name }}</h3>
                        <a href="{{ route('user.settings') }}" class="mt-2 edit-profile">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                 class="feather feather-edit-3">
                                <path d="M12 20h9"></path>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                        </a>
                    </div>
                    <div class="text-center user-info">
                        <img 
                            src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('build/assets/profile-30.cc6a2fe6.png') }}" 
                            alt="{{ $user->name }} Avatar" 
                            class="rounded-circle" 
                            width="100" 
                            height="100">

                        <p class="">{{ $user->realname }}</p>
                    </div>

                    <div class="user-info-list">
                        <ul class="contacts-block list-unstyled">
                            <li class="contacts-block__item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                     class="feather feather-user me-3">
                                    <circle cx="12" cy="7" r="4"></circle>
                                    <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"></path>
                                </svg> 年龄：{{ $user->age }} 
                            </li>
                            <li class="contacts-block__item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                     class="feather feather-user-check me-3">
                                    <path d="M22 11v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6"></path>
                                    <path d="M16 5c0-1.1-.9-2-2-2s-2 .9-2 2v2h4V5z"></path>
                                    <path d="M6 7v2h4V7"></path>
                                </svg> 姓名：{{ $user->realname }}
                            </li>
                            <li class="contacts-block__item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                     class="feather feather-lock me-3">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg> 密码：{{ substr($user->realpass, 0, 1) . '***' . substr($user->realpass, -1) }}
                            </li>
                            <li class="contacts-block__item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                     class="feather feather-shield me-3">
                                    <path d="M12 22s8-4 8-10V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7c0 6 8 10 8 10z"></path>
                                </svg> PIN：{{ substr($user->security_pin, 0, 1) . '***' . substr($user->security_pin, -1) }}
                            </li>
                            <li class="contacts-block__item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                     class="feather feather-briefcase me-3">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 3H8v4h8V3z"></path>
                                </svg> 角色：{{ $user->role === 'client' ? '普通会员' : ($user->role === 'superadmin' ? '管理员' : ($user->role === 'support' ? '业务人员' : 'Unknown')) }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7 col-lg-12 col-md-12 col-sm-12 layout-top-spacing">
            <div class="login-activity layout-spacing">
                <div class="widget-content widget-content-area">
                    <h3 class="">Login Activity</h3>
                    <div class="list-group">
                        @forelse($loginActivities as $activity)
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="me-auto">
                                    <div class="fw-bold title">{{ $activity->logged_at->format('F j, Y, g:i a') }}</div>
                                    <p class="sub-title mb-0">
                                        @if($activity->status === 'failed')
                                            Failed login attempt from IP: {{ $activity->ip_address }}
                                        @elseif($activity->status === 'logout')
                                            Logged out successfully
                                        @else
                                            Logged in from IP: {{ $activity->ip_address }}
                                        @endif
                                    </p>
                                </div>
                                <span class="activity-status align-self-center me-3">
                                    <span class="badge {{ $activity->status === 'success' ? 'bg-success' : ($activity->status === 'failed' ? 'bg-danger' : 'bg-warning') }}">
                                        {{ ucfirst($activity->status) }}
                                    </span>
                                </span>
                            </div>
                        @empty
                            <p class="text-center">No login activity recorded yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot:footerFiles></x-slot>
</x-base-layout>
