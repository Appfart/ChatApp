<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        批量生成客服
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!-- Add required CSS files -->
        <link href="{{ asset('css/custom-styles.css') }}" rel="stylesheet">
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <!-- BREADCRUMB -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">客服大厅</a></li>
                <li class="breadcrumb-item active" aria-current="page">批量生成客服</li>
            </ol>
        </nav>
    </div>
    <!-- /BREADCRUMB -->

    <!-- Content Area -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row layout-top-spacing">
        <div class="col-lg-8 col-md-10 col-sm-12 layout-spacing mx-auto">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">
                    <div class="row">
                        <div class="col-12">
                            <h4>客服/业务员</h4>
                            <p class="text-muted" style="margin-left:15px">批量生产客服业务员账号</p>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area">
                    <form method="POST" action="{{ route('user.store') }}">
                        @csrf

                        <!-- Number of Users -->
                        <div class="form-group">
                            <label for="user_count" class="form-label">Number of Users</label>
                            <input 
                                type="number" 
                                class="form-control" 
                                id="user_count" 
                                name="user_count" 
                                placeholder="Enter the number of users to create"
                                min="1" 
                                required>
                        </div>

                        <!-- Default Password -->
                        <div class="form-group">
                            <label for="password" class="form-label">Default Password</label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="Set a default password for users" 
                                required>
                            <small class="text-muted">Password must be at least 6 characters long.</small>
                        </div>

                        <!-- Default Security PIN -->
                        <div class="form-group">
                            <label for="security_pin" class="form-label">Default Security PIN</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="security_pin" 
                                name="security_pin" 
                                maxlength="6" 
                                placeholder="Set a 6-digit PIN"
                                required>
                            <small class="text-muted">Enter a numeric 6-digit PIN.</small>
                        </div>

                        <!-- Referral -->
                        <div class="form-group">
                            <label for="referral" class="form-label">Referral (Existing User)</label>
                            <select class="form-select" id="referral" name="referral" required>
                                <option value="" disabled selected>Select a referral</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }} ({{ $user->referral_link }}) -> {{ $user->realname }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Choose an existing user as the referral.</small>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Create Users
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /Content Area -->

    <!-- BEGIN CUSTOM SCRIPTS FILE -->
    <x-slot:footerFiles>
        <!-- Add any required JS files -->
        <script src="{{ asset('js/custom-scripts.js') }}"></script>
    </x-slot>
    <!-- END CUSTOM SCRIPTS FILE -->

</x-base-layout>
