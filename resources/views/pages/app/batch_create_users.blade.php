<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        Batch Create Users
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!-- Add any required CSS files here -->
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <!-- BREADCRUMB -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Batch Create Users</li>
            </ol>
        </nav>
    </div>
    <!-- /BREADCRUMB -->

    <div class="row layout-top-spacing">
        <div class="col-lg-6 col-sm-6 col-6 layout-spacing">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">
                    <div class="row">
                        <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                            <h4>Batch Create Users</h4>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area">
                    <form method="POST" action="{{ route('batch.create.users') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="user_count" class="form-label">Number of Users</label>
                            <input type="number" class="form-control" id="user_count" name="user_count" required>
                        </div>
                    
                        <div class="mb-3">
                            <label for="password" class="form-label">Default Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    
                        <div class="mb-3">
                            <label for="security_pin" class="form-label">Default Security PIN</label>
                            <input type="text" class="form-control" id="security_pin" name="security_pin" maxlength="6" required>
                        </div>
                    
                        <div class="mb-3">
                            <label for="referral" class="form-label">Referral (Existing User)</label>
                            <select class="form-select" id="referral" name="referral" required>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->referral_link }}) - {{ $user->realname }}</option>
                                @endforeach
                            </select>
                        </div>
                    
                        <button type="submit" class="btn btn-primary">Create Users</button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!--  BEGIN CUSTOM SCRIPTS FILE  -->
    <x-slot:footerFiles>
        <!-- Add any required JS files here -->
    </x-slot>
    <!--  END CUSTOM SCRIPTS FILE  -->

</x-base-layout>
