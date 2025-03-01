<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        批量创建用户
    </x-slot:pageTitle>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!-- 添加所需的 CSS 文件 -->
    </x-slot:headerFiles>
    <!-- END GLOBAL MANDATORY STYLES -->

    <!-- 面包屑导航 -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">仪表盘</a></li>
                <li class="breadcrumb-item active" aria-current="page">批量创建用户</li>
            </ol>
        </nav>
    </div>
    <!-- /面包屑导航 -->

    <div class="row layout-top-spacing">
        <div class="col-lg-6 col-sm-6 col-6 layout-spacing">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">
                    <div class="row">
                        <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                            <h4>批量创建用户</h4>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area">
                    <form method="POST" action="{{ route('batch.create.users') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="user_count" class="form-label">用户数量</label>
                            <input type="number" class="form-control" id="user_count" name="user_count" required>
                        </div>
                    
                        <div class="mb-3">
                            <label for="password" class="form-label">默认密码</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    
                        <div class="mb-3">
                            <label for="security_pin" class="form-label">默认安全 PIN</label>
                            <input type="text" class="form-control" id="security_pin" name="security_pin" maxlength="6" required>
                        </div>
                    
                        <div class="mb-3">
                            <label for="referral" class="form-label">推荐人（现有用户）</label>
                            <select class="form-select" id="referral" name="referral" required>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->referral_link }}) - {{ $user->realname }}</option>
                                @endforeach
                            </select>
                        </div>
                    
                        <button type="submit" class="btn btn-primary">创建用户</button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!--  BEGIN CUSTOM SCRIPTS FILE  -->
    <x-slot:footerFiles>
        <!-- 添加所需的 JS 文件 -->
    </x-slot:footerFiles>
    <!--  END CUSTOM SCRIPTS FILE  -->

</x-base-layout>