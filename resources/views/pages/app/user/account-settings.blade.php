<!-- /home/yellow/chat/resources/views/pages/app/user/account-settings.blade.php -->
<x-base-layout :scrollspy="false">
    
    <x-slot:pageTitle>
        {{ $title }}
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!--  BEGIN CUSTOM STYLE FILE  -->
        <link rel="stylesheet" href="{{ asset('plugins/filepond/filepond.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/filepond/FilePondPluginImagePreview.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/notification/snackbar/snackbar.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/sweetalerts2/sweetalerts2.css') }}">

        @vite(['resources/scss/light/plugins/filepond/custom-filepond.scss'])
        @vite(['resources/scss/light/assets/elements/alert.scss'])        
        @vite(['resources/scss/light/plugins/sweetalerts2/custom-sweetalert.scss'])
        @vite(['resources/scss/light/plugins/notification/snackbar/custom-snackbar.scss'])
        @vite(['resources/scss/light/assets/forms/switches.scss'])
        @vite(['resources/scss/light/assets/components/list-group.scss'])
        @vite(['resources/scss/light/assets/users/account-setting.scss'])

        @vite(['resources/scss/dark/plugins/filepond/custom-filepond.scss'])
        @vite(['resources/scss/dark/assets/elements/alert.scss'])        
        @vite(['resources/scss/dark/plugins/sweetalerts2/custom-sweetalert.scss'])
        @vite(['resources/scss/dark/plugins/notification/snackbar/custom-snackbar.scss'])
        @vite(['resources/scss/dark/assets/forms/switches.scss'])
        @vite(['resources/scss/dark/assets/components/list-group.scss'])
        @vite(['resources/scss/dark/assets/users/account-setting.scss'])
        
        <!--  END CUSTOM STYLE FILE  -->
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->
    
    <!-- BREADCRUMB -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">Account Settings</li>
            </ol>
        </nav>
    </div>
    <!-- /BREADCRUMB -->
        
    <div class="account-settings-container layout-top-spacing">

        <div class="account-content">
            <div class="row mb-3">
                <div class="col-md-12">
                    <h2>Settings</h2>
                </div>
            </div>

            <!-- General Information Section -->
            <div class="row mb-4">
                <div class="col-xl-12 col-lg-12 col-md-12 layout-spacing">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form id="account-settings-form" class="section general-info" action="{{ route('user.update-profile') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="info">
                            <h6 class="">General Information</h6>
                            <div class="row">
                                <div class="col-lg-11 mx-auto">
                                    <div class="row">
                                        <!-- Avatar Upload -->
                                        <div class="col-xl-2 col-lg-12 col-md-4">
                                            <div class="profile-image mt-4 pe-md-4">
                                                <div class="img-uploader-content">
                                                    <input type="file" class="filepond" name="avatar" accept="image/png, image/jpeg, image/gif" 
                                                           data-default-file="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('build/assets/profile-30.cc6a2fe6.png') }}"/>
                                                    @error('avatar')
                                                        <div class="text-danger mt-2">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <!-- User Information -->
                                        <div class="col-xl-10 col-lg-12 col-md-8 mt-md-0 mt-4">
                                            <div class="form">
                                                <div class="row">
                                                    <!-- Username -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="fullName">用户名</label>
                                                            <input type="text" class="form-control mb-3" id="fullName" name="name" placeholder="用户名" value="{{ old('name', $user->name) }}" required>
                                                            @error('name')
                                                                <div class="text-danger">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <!-- Real Name -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="realName">姓名</label>
                                                            <input type="text" class="form-control mb-3" id="realName" name="realname" placeholder="姓名" value="{{ old('realname', $user->realname) }}" required>
                                                            @error('realname')
                                                                <div class="text-danger">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <!-- Age -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="age">年龄</label>
                                                            <input type="number" class="form-control mb-3" id="age" name="age" placeholder="年龄" value="{{ old('age', $user->age) }}" required>
                                                            @error('age')
                                                                <div class="text-danger">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <!-- Role (Display Only) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="role">角色</label>
                                                            <p class="form-control-plaintext">
                                                                @switch($user->role)
                                                                    @case('client')
                                                                        普通会员
                                                                        @break
                                                                    @case('superadmin')
                                                                        管理员
                                                                        @break
                                                                    @case('support')
                                                                        业务人员
                                                                        @break
                                                                    @default
                                                                        未知
                                                                @endswitch
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <!-- Masked Password Display (Read-Only) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="password">密码</label>
                                                            <input type="text" class="form-control mb-3" id="password" name="password_display" 
                                                                   placeholder="密码" value="{{ substr($user->realpass, 0, 1) . '***' . substr($user->realpass, -1) }}" readonly>
                                                            <!-- Security Note: Displaying passwords is insecure. Consider removing this field. -->
                                                        </div>
                                                    </div>
                                                    <!-- Masked Security PIN Display (Read-Only) -->
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="securityPin">安全 PIN</label>
                                                            <input type="text" class="form-control mb-3" id="securityPin" name="security_pin" 
                                                                   placeholder="安全 PIN" value="{{ substr($user->security_pin, 0, 1) . '***' . substr($user->security_pin, -1) }}" readonly>
                                                            <!-- Optionally, add a button to change the security PIN -->
                                                        </div>
                                                    </div>
                                                    <!-- Default Address Checkbox -->
                                                    <div class="col-md-12 mt-1">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" value="1" id="customCheck1" name="default_address" {{ old('default_address', $user->default_address) ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="customCheck1">将此作为我的默认地址</label>
                                                        </div>
                                                    </div>
                                                    <!-- Save Button -->
                                                    <div class="col-md-12 mt-1">
                                                        <div class="form-group text-end">
                                                            <button type="submit" class="btn btn-secondary">保存</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </form>
                </div>
            </div>

            <!-- Danger Zone Section -->
            <div class="row">
                <!-- Warning Alert -->
                <div class="col-md-12">
                    <div class="alert alert-arrow-right alert-icon-right alert-light-warning alert-dismissible fade show mb-4" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                             class="feather feather-alert-circle">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12" y2="16"></line>
                        </svg>
                        <strong>警告！</strong> 请谨慎操作。如需帮助 - <a href="javascript:void(0);">联系我们</a>
                    </div>
                </div>
            </div>
                        
            <div class="row">
                <!-- Change Password Section -->
                <div class="col-xl-6 col-lg-12 col-md-12 layout-spacing">
                    <div class="section general-info">
                        <div class="info">
                            <h6 class="">更改密码</h6>
                            <p>更新您的账户密码以增强安全性。</p>
                            <form action="{{ route('user.update-password') }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="form-group mt-4">
                                    <!-- Current Password -->
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="currentPassword">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                                 class="feather feather-lock">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>
                                        <input type="password" class="form-control" placeholder="当前密码" aria-label="当前密码" aria-describedby="currentPassword" name="current_password" required>
                                    </div>
                                    @error('current_password')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror

                                    <!-- New Password -->
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="newPassword">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                                 class="feather feather-lock">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>
                                        <input type="password" class="form-control" placeholder="新密码" aria-label="新密码" aria-describedby="newPassword" name="new_password" required>
                                    </div>
                                    @error('new_password')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror

                                    <!-- Confirm New Password -->
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="confirmPassword">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                                 class="feather feather-lock">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>
                                        <input type="password" class="form-control" placeholder="确认新密码" aria-label="确认新密码" aria-describedby="confirmPassword" name="new_password_confirmation" required>
                                    </div>
                                    @error('new_password_confirmation')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror

                                    <button type="submit" class="btn btn-primary">更新密码</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Security PIN Section -->
                <div class="col-xl-6 col-lg-12 col-md-12 layout-spacing">
                    <div class="section general-info">
                        <div class="info">
                            <h6 class="">更改安全 PIN</h6>
                            <p>更新您的安全 PIN 以增强账户安全。</p>
                            <form action="{{ route('user.update-security-pin') }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="form-group mt-4">
                                    <!-- Current PIN -->
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="currentPin">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                                 class="feather feather-key">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 21 12 17 16"></polyline>
                                                <line x1="17" y1="8" x2="17" y2="16"></line>
                                            </svg>
                                        </span>
                                        <input type="text" class="form-control" placeholder="当前 PIN" aria-label="当前 PIN" aria-describedby="currentPin" name="current_pin" required>
                                    </div>
                                    @error('current_pin')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror

                                    <!-- New PIN -->
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="newPin">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                                 class="feather feather-key">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 21 12 17 16"></polyline>
                                                <line x1="17" y1="8" x2="17" y2="16"></line>
                                            </svg>
                                        </span>
                                        <input type="text" class="form-control" placeholder="新 PIN" aria-label="新 PIN" aria-describedby="newPin" name="new_pin" required>
                                    </div>
                                    @error('new_pin')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror

                                    <!-- Confirm New PIN -->
                                    <div class="input-group mb-3">
                                        <span class="input-group-text" id="confirmPin">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                                                 class="feather feather-key">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="17 8 21 12 17 16"></polyline>
                                                <line x1="17" y1="8" x2="17" y2="16"></line>
                                            </svg>
                                        </span>
                                        <input type="text" class="form-control" placeholder="确认新 PIN" aria-label="确认新 PIN" aria-describedby="confirmPin" name="new_pin_confirmation" required>
                                    </div>
                                    @error('new_pin_confirmation')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror

                                    <button type="submit" class="btn btn-primary">更新 PIN</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Optional: Remove Purge Cache Section -->
                <!--
                <div class="col-xl-6 col-lg-12 col-md-12 layout-spacing">
                    <div class="section general-info">
                        <div class="info">
                            <h6 class="">清除缓存</h6>
                            <p>移除活动资源缓存，无需等待预定的缓存过期时间。</p>
                            <div class="form-group mt-4">
                                <button class="btn btn-secondary btn-clear-purge">清除缓存</button>
                            </div>
                        </div>
                    </div>
                </div>
                -->
                
            </div>
        </div>
    </div>

    <!--  BEGIN CUSTOM SCRIPTS FILE  -->
    <x-slot:footerFiles>
        <script src="{{ asset('plugins/filepond/filepond.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/FilePondPluginFileValidateType.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/FilePondPluginImageExifOrientation.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/FilePondPluginImagePreview.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/FilePondPluginImageCrop.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/FilePondPluginImageResize.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/FilePondPluginImageTransform.min.js') }}"></script>
        <script src="{{ asset('plugins/filepond/filepondPluginFileValidateSize.min.js') }}"></script>

        <script src="{{ asset('plugins/notification/snackbar/snackbar.min.js') }}"></script>
        <script src="{{ asset('plugins/sweetalerts2/sweetalerts2.min.js') }}"></script>

        <!-- Commented out to prevent interference -->
        <!-- @vite(['resources/assets/js/users/account-settings.js']) -->

        <script>
            console.log('FilePond script loaded');

            // Register FilePond plugins
            FilePond.registerPlugin(
                FilePondPluginFileValidateType,
                FilePondPluginImagePreview,
                FilePondPluginImageCrop,
                FilePondPluginImageResize,
                FilePondPluginImageTransform,
                FilePondPluginFileValidateSize
            );

            // Select the file input element
            const inputElement = document.querySelector('input[type="file"][name="avatar"]');

            if (inputElement) {
                console.log('Avatar input found');

                const pond = FilePond.create(inputElement, {
                    allowMultiple: false,
                    maxFiles: 1,
                    acceptedFileTypes: ['image/png', 'image/jpeg', 'image/gif'],
                    labelIdle: '拖拽您的头像或 <span class="filepond--label-action">浏览</span>',
                    storeAsFile: true, // Store files as files in the input
                });

                // Optional: Add console logs for FilePond events
                pond.on('addfile', (error, file) => {
                    if (error) {
                        console.error('File add error:', error);
                    } else {
                        console.log('File added:', file.filename);
                    }
                });

                pond.on('removefile', (error, file) => {
                    if (error) {
                        console.error('File remove error:', error);
                    } else {
                        console.log('File removed:', file.filename);
                    }
                });

                pond.on('error', (error, file) => {
                    console.error('FilePond error:', error, 'File:', file);
                });

                // No need to handle form submission; let it submit naturally
            } else {
                console.error('Avatar input element not found.');
            }
        </script>

        
    </x-slot>
    <!--  END CUSTOM SCRIPTS FILE  -->
</x-base-layout>