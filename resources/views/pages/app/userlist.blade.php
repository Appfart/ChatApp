<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        {{$title}}
    </x-slot>

    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/table/datatable/datatables.css') }}">
        @vite(['resources/scss/light/plugins/table/datatable/dt-global_style.scss'])
        @vite(['resources/scss/dark/plugins/table/datatable/dt-global_style.scss'])
    </x-slot>

    <!-- Filter Form -->
    <div class="row mb-4 mt-4">
        <!-- Input Row -->
        <div class="col-12">
            <form method="GET" action="{{ route('user.list') }}">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="用户名">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="referral" value="{{ request('referral') }}" class="form-control" placeholder="推荐人姓名">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="birthday" value="{{ request('birthday') }}" class="form-control" placeholder="出生日期">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="security_pin" value="{{ request('security_pin') }}" class="form-control" placeholder="安全码">
                    </div>
                </div>
                <!-- Button Row -->
                <div class="row mt-3">
                    <div class="col-md-12 d-flex justify-content-start">
                        <button type="submit" class="btn btn-primary me-2">搜索</button>
                        <a href="{{ route('user.list') }}" class="btn btn-secondary">重置</a>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- User List Table -->
    <div class="row layout-top-spacing">
        <div class="col-xl-12 col-lg-12 col-sm-12 layout-spacing">
            <div class="widget-content widget-content-area br-8">
                <table id="zero-config" class="table table-striped dt-table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>登入名</th>
                            <th>用户姓名</th>
                            <th>推荐用户</th>
                            <!--<th>年龄</th>
                            <th>安全码 Pin</th>-->
                            <th>钱包余额</th>
                            <th>钱包显示</th>
                            <th>显示开关</th>
                            <th>註冊时间</th>
                            <th>最后登入</th>
                            <th>推荐码</th>
                            <th>操作按钮</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->realname }}</td>
                                <td>{{ $user->upline_name ?? 'N/A' }}</td>
                                <!--<td>{{ $user->age }}</td>
                                <td>{{ $user->security_pin }}</td>-->
                                <td>{{ $user->amount ?? '0.00' }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $user->type }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $user->status == 1 ? 'bg-success' : 'bg-danger' }}">
                                        {{ $user->status == 1 ? '开' : '关' }}
                                    </span>
                                </td>
                                <td>{{ $user->created_at }}</td>
                                <td>{{ $user->updated_at }}</td>
                                <td>{{ $user->referral_link }}</td>
                                <td>
                                    @if(Auth::user()->role === 'superadmin')
                                        <a href="{{ route('user.edit', $user->id) }}" class="btn btn-sm btn-warning">编辑</a>
                                        <form action="{{ route('user.chat', $user->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('您确定要与此用户开始聊天吗？')">聊天</button>
                                        </form>
                                    @else
                                        <button class="btn btn-sm btn-warning" disabled>编辑</button>
                                        <button class="btn btn-sm btn-primary" disabled>聊天</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div>

    <x-slot:footerFiles>
        <script src="{{ asset('plugins/global/vendors.min.js') }}"></script>
        @vite(['resources/assets/js/custom.js'])
        <script src="{{ asset('plugins/table/datatable/datatables.js') }}"></script>
        <script>
            $('#zero-config').DataTable({
                "dom": "<'dt--top-section'<'row'<'col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center'l><'col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3'f>>>" +
                       "<'table-responsive'tr>" +
                       "<'dt--bottom-section d-sm-flex justify-content-sm-between text-center'<'dt--pages-count mb-sm-0 mb-3'i><'dt--pagination'p>>",
                "oLanguage": {
                    "oPaginate": { 
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" ...></svg>', 
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" ...></svg>' 
                    },
                    "sInfo": "Showing page _PAGE_ of _PAGES_",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" ...></svg>',
                    "sSearchPlaceholder": "Search...",
                    "sLengthMenu": "Results : _MENU_",
                },
                "lengthMenu": [7, 10, 20, 50],
                "pageLength": 10 
            });
        </script>
    </x-slot>
</x-base-layout>
