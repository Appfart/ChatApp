<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>充值</x-slot>
    
    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/table/datatable/datatables.css') }}">
        @vite(['resources/scss/light/plugins/table/datatable/dt-global_style.scss'])
        @vite(['resources/scss/dark/plugins/table/datatable/dt-global_style.scss'])
    </x-slot>
    
    <!-- BREADCRUMB -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">存款</a></li>
                <li class="breadcrumb-item active" aria-current="page">存款列表</li>
            </ol>
        </nav>
    </div>
    <!-- /BREADCRUMB -->

    <div class="container">
        <h1 class="mt-4">存款记录</h1>
    
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
    
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-striped mt-4 bg-white" id="deposits-table">
            <thead>
                <tr>
                    <th>交易编号</th>
                    <th>用户</th>
                    <th>金额</th>
                    <th>银行名</th>
                    <th>账户名</th>
                    <th>账户号</th>
                    <th>支行</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deposits as $deposit)
                    <tr>
                        <td>{{ $deposit->txid }}</td>
                        <td>{{ $deposit->user->name ?? '无' }}</td>
                        <td>￥{{ number_format($deposit->amount, 2) }}</td>
                        <td>{{ $deposit->bankname }}</td>
                        <td>{{ $deposit->accname }}</td>
                        <td>{{ $deposit->accno }}</td>
                        <td>{{ $deposit->branch }}</td>
                        <td>
                            <span class="badge bg-{{ $deposit->status == 'pending' ? 'warning' : ($deposit->status == 'complete' ? 'success' : 'danger') }}">
                                {{ $deposit->status == 'pending' ? '待处理' : ($deposit->status == 'complete' ? '已完成' : '已拒绝') }}
                            </span>
                        </td>
                        <td>{{ $deposit->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if ($deposit->status == 'pending')
                                <form action="{{ route('deposits.approve', $deposit->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">批准</button>
                                </form>
                                <form action="{{ route('deposits.reject', $deposit->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm">拒绝</button>
                                </form>
                            @else
                                <button class="btn btn-secondary btn-sm" disabled>已处理</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center">未找到存款记录。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    
    <!--  BEGIN CUSTOM SCRIPTS FILE  -->
    <x-slot:footerFiles>
        <script src="{{ asset('plugins/global/vendors.min.js') }}"></script>
        @vite(['resources/assets/js/custom.js'])
        <script src="{{ asset('plugins/table/datatable/datatables.js') }}"></script>

        <script>
            $('#deposits-table').DataTable({
                "dom": "<'dt--top-section'<'row'<'col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center'l><'col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3'f>>>" +
                       "<'table-responsive'tr>" +
                       "<'dt--bottom-section d-sm-flex justify-content-sm-between text-center'<'dt--pages-count  mb-sm-0 mb-3'i><'dt--pagination'p>>",
                "oLanguage": {
                    "oPaginate": { 
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>', 
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>' 
                    },
                    "sInfo": "当前第 _PAGE_ 页，共 _PAGES_ 页",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                    "sSearchPlaceholder": "搜索...",
                    "sLengthMenu": "显示 _MENU_ 条结果",
                },
                "stripeClasses": [],
                "lengthMenu": [7, 10, 20, 50],
                "pageLength": 10 
            });
        </script>
    </x-slot>
    <!--  END CUSTOM SCRIPTS FILE  -->
</x-base-layout>
