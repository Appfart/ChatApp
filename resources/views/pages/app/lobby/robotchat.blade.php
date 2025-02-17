<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>我的机器人</x-slot>

    <x-slot:headerFiles>
        <!-- 在这里包含任何额外的 CSS 或样式 -->
    </x-slot>

    <div class="container" style="margin-top:50px">
        <h1>我的机器人</h1>

        <!-- 显示主用户卡片 -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-header d-flex align-items-center bg-primary text-white">
                        <img src="{{ auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : asset('default-avatar.png') }}" 
                             alt="头像" 
                             class="rounded-circle" 
                             style="width: 40px; height: 40px; margin-right: 10px;">
                        <strong>{{ auth()->user()->realname }} 【{{ auth()->user()->name }}】</strong>
                    </div>
                    <div class="card-body">
                        <p>推荐码: N/A</p>
                        <p>创建日期: {{ auth()->user()->created_at->format('Y-m-d') }}</p>
                        <form action="{{ route('support.impersonate', auth()->id()) }}" method="POST" target="_blank">
                            @csrf
                            <button type="submit" class="btn btn-primary">以主用户身份登录</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 机器人列表 -->
        <div class="row">
            @foreach($robots as $robot)
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <img src="{{ $robot->avatar ? asset('storage/' . $robot->avatar) : asset('default-avatar.png') }}" 
                                 alt="头像" 
                                 class="rounded-circle" 
                                 style="width: 40px; height: 40px; margin-right: 10px;">
                            <a href="#" class="robot-edit-link" data-id="{{ $robot->id }}" 
                               data-name="{{ $robot->name }}" 
                               data-realname="{{ $robot->realname }}" 
                               data-securitypin="{{ $robot->security_pin }}" 
                               data-avatar="{{ $robot->avatar }}">
                               {{ $robot->realname }}【{{ $robot->name }}】
                            </a>
                        </div>
                        <div class="card-body">
                            <p>推荐码: {{ $robot->referral_link }}</p>
                            <p>创建日期: {{ $robot->created_at->format('Y-m-d') }}</p>
                            <form action="{{ route('support.impersonate', $robot->id) }}" method="POST" target="_blank">
                                @csrf
                                <button type="submit" class="btn btn-primary">登入机器人</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- 编辑机器人模态框 -->
    <div class="modal fade" id="editRobotModal" tabindex="-1" role="dialog" aria-labelledby="editRobotModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content bg-white">
                <form id="editRobotForm" action="{{ route('robot.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editRobotModalLabel">编辑机器人</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="robot_id" id="robotId">
                        <div class="form-group">
                            <label for="robotName">用户名</label>
                            <input type="text" class="form-control" name="name" id="robotName" required>
                        </div>
                        <div class="form-group">
                            <label for="robotRealName">真实姓名</label>
                            <input type="text" class="form-control" name="realname" id="robotRealName">
                        </div>
                        <div class="form-group">
                            <label for="robotSecurityPin">安全 PIN</label>
                            <input type="text" class="form-control" name="security_pin" id="robotSecurityPin" maxlength="6">
                        </div>
                        <div class="form-group">
                            <label for="robotPassword">新密码</label>
                            <input type="password" class="form-control" name="password" id="robotPassword">
                        </div>
                        <div class="form-group">
                            <label for="robotAvatar">头像</label>
                            <input type="file" class="form-control" name="avatar" id="robotAvatar" accept="image/*">
                            <img id="robotAvatarPreview" src="" alt="头像预览" class="mt-2" style="max-width: 100px; max-height: 100px;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存更改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById('editRobotModal');
                const modal = new bootstrap.Modal(modalElement); // 初始化 Bootstrap 模态框实例
                const form = document.getElementById('editRobotForm');
                const isSuperAdmin = @json(Auth::user()->role === 'superadmin');
                const isSupport = @json(Auth::user()->role === 'support');

                // 处理机器人编辑链接点击事件
                document.querySelectorAll('.robot-edit-link').forEach(link => {
                    link.addEventListener('click', function (event) {
                        event.preventDefault();

                        // 填充模态框中的机器人详情
                        document.getElementById('robotId').value = this.dataset.id;
                        document.getElementById('robotName').value = this.dataset.name;
                        document.getElementById('robotRealName').value = this.dataset.realname;
                        document.getElementById('robotSecurityPin').value = this.dataset.securitypin;

                        const avatarPreview = document.getElementById('robotAvatarPreview');
                        const avatarUrl = this.dataset.avatar ? `/storage/${this.dataset.avatar}` : '/default-avatar.png';
                        avatarPreview.src = avatarUrl;

                        // 根据用户角色启用或禁用表单字段
                        if (!isSuperAdmin && !isSupport) {
                            // Disable all input fields and the submit button
                            form.querySelectorAll('input').forEach(input => {
                                input.disabled = true;
                            });
                            form.querySelector('button[type="submit"]').disabled = true;
                        } else {
                            // 启用所有输入字段
                            form.querySelectorAll('input').forEach(input => {
                                input.disabled = false;
                            });
                            // 启用提交按钮
                            form.querySelector('button[type="submit"]').disabled = false;
                        }

                        // 显示模态框
                        modal.show();
                    });
                });

                // 头像预览
                document.getElementById('robotAvatar').addEventListener('change', function () {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            document.getElementById('robotAvatarPreview').src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            });
        </script>
    </x-slot>
</x-base-layout>

