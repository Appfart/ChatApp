<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        批量生成客服
    </x-slot:pageTitle>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!-- 添加所需的 CSS 文件 -->
        <link href="{{ asset('css/custom-styles.css') }}" rel="stylesheet">
    </x-slot:headerFiles>
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

    <!-- 内容区域 -->
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
                            <p class="text-muted" style="margin-left:15px">批量生成客服业务员账号</p>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area">
                    <form method="POST" action="{{ route('user.store') }}">
                        @csrf

                        <!-- 用户数量 -->
                        <div class="form-group">
                            <label for="user_count" class="form-label">用户数量</label>
                            <input 
                                type="number" 
                                class="form-control" 
                                id="user_count" 
                                name="user_count" 
                                placeholder="输入要创建的用户数量"
                                min="1" 
                                required>
                        </div>

                        <!-- 默认密码 -->
                        <div class="form-group">
                            <label for="password" class="form-label">默认密码</label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                placeholder="为用户设置默认密码" 
                                required>
                            <small class="text-muted">密码至少需要6个字符。</small>
                        </div>

                        <!-- 默认安全 PIN -->
                        <div class="form-group">
                            <label for="security_pin" class="form-label">默认安全 PIN</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="security_pin" 
                                name="security_pin" 
                                maxlength="6" 
                                placeholder="设置6位PIN码"
                                required>
                            <small class="text-muted">请输入6位数字的PIN码。</small>
                        </div>

                        <!-- 推荐人 -->
                        <div class="form-group">
                            <label for="referral" class="form-label">推荐人（现有用户）</label>
                            <select class="form-select" id="referral" name="referral" required>
                                <option value="" disabled selected>选择推荐人</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }} ({{ $user->referral_link }}) -> {{ $user->realname }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">选择一位现有用户作为推荐人。</small>
                        </div>

                        <!-- 提交按钮 -->
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                创建客服
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- /内容区域 -->

    <!-- BEGIN CUSTOM SCRIPTS FILE -->
    <x-slot:footerFiles>
        <!-- 添加所需的 JS 文件 -->
        <script src="{{ asset('js/custom-scripts.js') }}"></script>
    </x-slot:footerFiles>
    <!-- END CUSTOM SCRIPTS FILE -->

</x-base-layout>