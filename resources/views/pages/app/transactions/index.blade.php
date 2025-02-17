<!-- resources/views/pages/app/transactions/index.blade.php -->

<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>
        {{ $title }} 
    </x-slot>
    
    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        <!--  BEGIN CUSTOM STYLE FILE  -->
        <link rel="stylesheet" href="{{ asset('plugins/table/datatable/datatables.css') }}">
        @vite(['resources/scss/light/plugins/table/datatable/dt-global_style.scss'])
        @vite(['resources/scss/dark/plugins/table/datatable/dt-global_style.scss'])
        <!--  END CUSTOM STYLE FILE  -->
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->
    
    <!-- BREADCRUMB -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">交易记录</a></li>
                <li class="breadcrumb-item active" aria-current="page">交易列表</li>
            </ol>
        </nav>
    </div>
    <!-- /BREADCRUMB -->

    <!-- CONTENT SECTION -->
    <div class="container">
        <h1 class="mt-4">{{ $title }}</h1>
    
        <!-- 成功消息 -->
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
    
        <!-- 错误消息 -->
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
    
        <!-- 交易表格 -->
        <table id="transactions-table" class="table table-striped mt-4">
            <thead>
                <tr>
                    <th>#</th>
                    <th>交易ID (txid)</th>
                    <th>用户</th>
                    <th>加减</th>
                    <th>金额</th>
                    <th>状态</th>
                    <th>类型</th>
                    <th>创建时间</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>{{ ($transactions->currentPage() - 1) * $transactions->perPage() + $loop->iteration }}</td>
                        <td>{{ $transaction->txid }}</td>
                        <td>{{ $transaction->user->name ?? '无' }}</td>
                        <td>
                            @if ($transaction->method === '+')
                                <span class="badge bg-success">+</span>
                            @elseif ($transaction->method === '-')
                                <span class="badge bg-danger">-</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($transaction->method) }}</span>
                            @endif
                        </td>
                        <td>{{ number_format($transaction->amount, 2) }}</td>
                        <td>
                            <span class="badge bg-{{ 
                                $transaction->status == 1 ? 'success' : 
                                ($transaction->status == 0 ? 'danger' : 'secondary') 
                            }}">
                                {{ $transaction->status == 1 ? '完成' : ($transaction->status == 0 ? '未激活' : '未知') }}
                            </span>
                        </td>
                        <td>{{ $transaction->type == "deposit" ? '充值' : ($transaction->type == "withdrawal" ? '提现' : '未知') }}</td>
                        <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">未找到任何交易记录。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    
        <!-- 分页链接 -->
        <div class="d-flex justify-content-center mt-4">
            {{ $transactions->links() }}
        </div>
    </div>
    <!-- END CONTENT SECTION -->

    <!--  BEGIN CUSTOM SCRIPTS FILE  -->
    <x-slot:footerFiles>
        <script src="{{ asset('plugins/global/vendors.min.js') }}"></script>
        @vite(['resources/assets/js/custom.js'])
        <script src="{{ asset('plugins/table/datatable/datatables.js') }}"></script>

        <script>
            $(document).ready(function() {
                $('#transactions-table').DataTable({
                    "dom": "<'dt--top-section'<'row'<'col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center'l><'col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3'f>>>" +
                           "<'table-responsive'tr>" +
                           "<'dt--bottom-section d-sm-flex justify-content-sm-between text-center'<'dt--pages-count  mb-sm-0 mb-3'i><'dt--pagination'p>>",
                    "oLanguage": {
                        "sProcessing": "处理中...",
                        "sLengthMenu": "显示 _MENU_ 项结果",
                        "sZeroRecords": "没有匹配结果",
                        "sInfo": "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
                        "sInfoEmpty": "显示第 0 至 0 项结果，共 0 项",
                        "sInfoFiltered": "(由 _MAX_ 项结果过滤)",
                        "sInfoPostFix": "",
                        "sSearch": "搜索:",
                        "sUrl": "",
                        "sEmptyTable": "表中数据为空",
                        "sLoadingRecords": "载入中...",
                        "sInfoThousands": ",",
                        "oPaginate": { 
                            "sFirst": "首页",
                            "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>', 
                            "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
                            "sLast": "尾页"
                        },
                        "oAria": {
                            "sSortAscending": ": 以升序排列此列",
                            "sSortDescending": ": 以降序排列此列"
                        },
                        "sSearchPlaceholder": "搜索...",
                        "sDecimal": "",
                        "sThousands": ",",
                        "sLengthMenu": "显示 _MENU_ 项结果",
                        "sLoadingRecords": "载入中...",
                        "sProcessing": "处理中...",
                        "sZeroRecords": "没有匹配结果",
                        "sInfo": "显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
                        "sInfoEmpty": "显示第 0 至 0 项结果，共 0 项",
                        "sInfoFiltered": "(由 _MAX_ 项结果过滤)",
                        "sInfoPostFix": "",
                        "sSearch": "搜索:",
                        "sUrl": "",
                        "sEmptyTable": "表中数据为空",
                        "sLoadingRecords": "载入中...",
                        "sInfoThousands": ","
                    },
                    "stripeClasses": [],
                    "lengthMenu": [7, 10, 20, 50],
                    "pageLength": 10,
                    "order": [[7, "desc"]], // 按创建时间降序排列
                    "columnDefs": [
                        { "orderable": true, "targets": [0, 1, 2, 3, 4, 5, 6, 7] },
                        { "orderable": false, "targets": [] } // 如果有不需要排序的列，可以在这里指定
                    ]
                });
            });
        </script>
    </x-slot>
    <!--  END CUSTOM SCRIPTS FILE  -->
</x-base-layout>