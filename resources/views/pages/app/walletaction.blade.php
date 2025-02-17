<!-- resources/views/pages/app/walletaction.blade.php -->

<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{$title}} 
    </x-slot>

    <!-- 开始全局必需样式 -->
    <x-slot:headerFiles>
        <!-- 在此处添加所需的 CSS 文件 -->
        <link rel="stylesheet" href="{{ asset('plugins/table/datatable/datatables.css') }}">
        @vite(['resources/scss/light/plugins/table/datatable/dt-global_style.scss'])
        @vite(['resources/scss/dark/plugins/table/datatable/dt-global_style.scss'])

        <!-- 引入 Select2 的 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    </x-slot>
    <!-- 结束全局必需样式 -->

    <!-- 面包屑导航 -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">仪表板</a></li>
                <li class="breadcrumb-item active" aria-current="page">钱包操作</li>
            </ol>
        </nav>
    </div>
    <!-- /面包屑导航 -->
    
    <div class="row layout-top-spacing">
        <!-- 存款表单 -->
        <div class="col-lg-4 col-sm-12 col-12 layout-spacing">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">                                
                    <div class="row">
                        <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                            <h4>存款</h4>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area p-3">
                    <form action="{{ route('wallet.action.deposit') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="depositUser" class="form-label">选择用户</label>
                            <select class="form-select searchable-select" id="depositUser" name="user_id" required>
                                <option></option> <!-- 用于占位符 -->
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->realname }}, {{ $user->referral_link }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="depositAmount" class="form-label">金额</label>
                            <input type="number" class="form-control" id="depositAmount" name="amount" placeholder="输入金额" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="depositMethod" class="form-label">方法</label>
                            <select class="form-select" id="depositMethod" name="method" required>
                                <option value="+" selected>追加</option>
                                <option value="-">扣除</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">提交</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 冻结账户表单 -->
        <div class="col-lg-4 col-sm-12 col-12 layout-spacing">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">                                
                    <div class="row">
                        <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                            <h4>冻结账户</h4>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area p-3">
                    <form action="{{ route('wallet.action.freeze') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="freezeUser" class="form-label">选择用户</label>
                            <select class="form-select searchable-select" id="freezeUser" name="user_id" required>
                                <option></option> <!-- 用于占位符 -->
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->realname }}, {{ $user->referral_link }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="freezeAmount" class="form-label">金额</label>
                            <input type="number" class="form-control" id="freezeAmount" name="amount" placeholder="输入金额" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="freezeMethod" class="form-label">方法</label>
                            <select class="form-select" id="freezeMethod" name="method" required>
                                <option value="+" selected>追加</option>
                                <option value="-">扣除</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">提交</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 调整钱包表单 -->
        <div class="col-lg-4 col-sm-12 col-12 layout-spacing">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">                                
                    <div class="row">
                        <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                            <h4>调整钱包</h4>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area p-3">
                    <form action="{{ route('wallet.action.adjust') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="adjustUser" class="form-label">选择用户</label>
                            <select class="form-select searchable-select" id="adjustUser" name="user_id" required>
                                <option></option> <!-- 用于占位符 -->
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->realname }}, {{ $user->referral_link }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="adjustAmount" class="form-label">金额</label>
                            <input type="number" class="form-control" id="adjustAmount" name="amount" placeholder="输入金额" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="adjustMethod" class="form-label">方法</label>
                            <select class="form-select" id="adjustMethod" name="method" required>
                                <option value="+" selected>追加</option>
                                <option value="-">扣除</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">提交</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
    
    发送推送通知按钮
        <div class="col-lg-4 col-sm-12 col-12 layout-spacing">
            <div class="statbox widget box box-shadow">
                <div class="widget-header">                                
                    <div class="row">
                        <div class="col-xl-12 col-md-12 col-sm-12 col-12">
                            <h4>发送推送通知</h4>
                        </div>
                    </div>
                </div>
                <div class="widget-content widget-content-area p-3">
                    <form action="{{ route('wallet.action.notify') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">发送推送通知</button>
                    </form>
                </div>
            </div>
        </div>

    <!-- 显示成功和错误消息 -->
    <div class="row">
        <div class="col-12">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
            @endif
        </div>
    </div>

    <!--  开始自定义脚本文件  -->
    <x-slot:footerFiles>
        <!-- 包含 Firebase 兼容脚本 -->
        <script src="https://www.gstatic.com/firebasejs/9.17.2/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/9.17.2/firebase-messaging-compat.js"></script>
        
        <!-- 引入 jQuery 和 Select2 -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            $(document).ready(function() {
                // 初始化 Select2
                $('.searchable-select').select2({
                    theme: 'bootstrap-5', // 使用 Bootstrap 5 主题
                    placeholder: '选择用户...',
                    allowClear: true,
                    width: '100%',
                    language: {
                        inputTooShort: function () {
                            return '请输入搜索内容';
                        },
                        noResults: function () {
                            return '未找到匹配的用户';
                        },
                        searching: function () {
                            return '搜索中…';
                        }
                    }
                });
            });

            // 初始化 Firebase
            const firebaseConfig = {
                apiKey: "AIzaSyBl9WH__ZWoWy6xhpk7D4S65gwsHX621IM",
                authDomain: "chatapp-76c82.firebaseapp.com",
                projectId: "chatapp-76c82",
                storageBucket: "chatapp-76c82.appspot.com",
                messagingSenderId: "218928639013",
                appId: "1:218928639013:web:b66ba3692347ca296763cc"
            };
        
            const firebaseApp = firebase.initializeApp(firebaseConfig);
        
            // 初始化 Firebase 消息
            const messaging = firebaseApp.messaging();

            // 请求权限并获取令牌的函数
            function requestPermission() {
                Notification.requestPermission()
                    .then((permission) => {
                        if (permission === 'granted') {
                            console.log('通知权限已授予。');
                            // 获取令牌
                            return messaging.getToken({ vapidKey: 'BDd6FajrDPlMMYS-IC4KELo2utSZYQ2q4zXDBQBI_K_XmLDNeAQKe2D-6dSjCpK5pz93B2hUIJh62kAI3eDxpzA' });
                        } else {
                            console.log('无法获取通知权限。');
                        }
                    })
                    .then((token) => {
                        if (token) {
                            console.log("设备令牌:", token);
                            // 将此令牌发送到 Laravel 后端以与用户关联
                            saveToken(token);
                        }
                    })
                    .catch((err) => {
                        console.error("Firebase 消息设置过程中出错:", err);
                    });
            }

            // 将令牌发送到后端的函数
            function saveToken(token) {
                fetch('{{ route('firebase.token.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ token: token }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('令牌保存成功。');
                    } else {
                        console.error('令牌保存失败。');
                    }
                })
                .catch((error) => {
                    console.error('保存令牌时出错:', error);
                });
            }

            // 在页面加载时调用 requestPermission
            requestPermission();

            // 处理接收的通知
            messaging.onMessage((payload) => {
                console.log("收到消息。", payload);
                // 显示通知
                new Notification(payload.notification.title, {
                    body: payload.notification.body,
                    icon: payload.notification.icon || 'https://chat.yellownft.xyz/build/assets/logo2.25baa396.svg',
                });
            });

            // 注册服务工作者
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/firebase-messaging-sw.js')
                    .then(function(registration) {
                        console.log('服务工作者已注册，范围:', registration.scope);
                        // 已移除 messaging.useServiceWorker(registration); 因为它已弃用
                    })
                    .catch(function(err) {
                        console.error('服务工作者注册失败:', err);
                    });
            }
        </script>
    </x-slot>
    <!--  结束自定义脚本文件  -->
</x-base-layout>
