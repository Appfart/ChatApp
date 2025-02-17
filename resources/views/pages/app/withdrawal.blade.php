<x-base-layout :scrollspy="false">

    <x-slot:pageTitle>提款</x-slot>

    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/table/datatable/datatables.css') }}">
        @vite(['resources/scss/light/plugins/table/datatable/dt-global_style.scss'])
        @vite(['resources/scss/dark/plugins/table/datatable/dt-global_style.scss'])
    </x-slot>

    <!-- 面包屑导航 -->
    <div class="page-meta">
        <nav class="breadcrumb-style-one" aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">提款</a></li>
                <li class="breadcrumb-item active" aria-current="page">提款列表</li>
            </ol>
        </nav>
    </div>
    <!-- /面包屑导航 -->

    <div class="container">
        <h1 class="mt-4">提款记录</h1>

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
            <table class="table table-striped mt-4 bg-white" id="withdrawals-table">
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
                    @forelse ($withdrawals as $withdrawal)
                        <tr>
                            <td>{{ $withdrawal->txid }}</td>
                            <td>{{ $withdrawal->user->name ?? '无' }}</td>
                            <td>{{ number_format($withdrawal->amount, 2) }}</td>
                            <td>{{ $withdrawal->bankname }}</td>
                            <td>{{ $withdrawal->accname }}</td>
                            <td>{{ $withdrawal->accno }}</td>
                            <td>{{ $withdrawal->branch }}</td>
                            <td>
                                <span class="badge bg-{{ $withdrawal->status == 'pending' ? 'warning' : ($withdrawal->status == 'complete' ? 'success' : 'danger') }}">
                                    {{ ucfirst($withdrawal->status) == 'Pending' ? '待处理' : (ucfirst($withdrawal->status) == 'Complete' ? '完成' : '拒绝') }}
                                </span>
                            </td>
                            <td>{{ $withdrawal->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                               @if ($withdrawal->status == 'rejected' || $withdrawal->status == 'complete')
                                    <button class="btn btn-primary btn-sm edit-withdrawal" 
                                        data-txid="{{ $withdrawal->txid }}" 
                                        data-amount="{{ $withdrawal->amount }}" 
                                        data-bankname="{{ $withdrawal->bankname }}" 
                                        data-accname="{{ $withdrawal->accname }}" 
                                        data-accno="{{ $withdrawal->accno }}">
                                        编辑
                                    </button>
                                    <button class="btn btn-success btn-sm" disabled>批准</button>
                                    <button class="btn btn-danger btn-sm" disabled>拒绝</button>
                                @elseif ($withdrawal->status == 'pending')
                                    @if(Auth::user()->role === 'superadmin')
                                        <button class="btn btn-primary btn-sm edit-withdrawal" 
                                            data-txid="{{ $withdrawal->txid }}" 
                                            data-amount="{{ $withdrawal->amount }}" 
                                            data-bankname="{{ $withdrawal->bankname }}" 
                                            data-accname="{{ $withdrawal->accname }}" 
                                            data-accno="{{ $withdrawal->accno }}">
                                            编辑
                                        </button>
                                        <button class="btn btn-success btn-sm approve-withdrawal" 
                                            data-txid="{{ $withdrawal->txid }}">
                                            批准
                                        </button>
                                        <button class="btn btn-danger btn-sm reject-withdrawal" 
                                            data-txid="{{ $withdrawal->txid }}" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal">
                                            拒绝
                                        </button>
                                    @else
                                        <button class="btn btn-success btn-sm approve-withdrawal" 
                                            data-txid="{{ $withdrawal->txid }}">
                                            批准
                                        </button>
                                        <button class="btn btn-danger btn-sm reject-withdrawal" 
                                            data-txid="{{ $withdrawal->txid }}" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal">
                                            拒绝
                                        </button>
                                    @endif
                                @else
                                    <button class="btn btn-secondary btn-sm" disabled>已处理</button>
                                @endif

                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center">未找到提款记录。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Rejection Reason -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-white">
                <form id="rejectForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectModalLabel">拒绝提款</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="txid" id="rejectTxid">
                        <div class="form-group">
                            <label for="reason">拒绝原因 (可选)</label>
                            <textarea class="form-control" name="method" id="rejectReason" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-danger">提交拒绝</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Editing Withdrawal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-white">
                <form id="editForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">编辑提款</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="txid" id="editTxid">
                        <div class="form-group">
                            <label for="editAmount">金额</label>
                            <input type="number" step="0.01" class="form-control" name="amount" id="editAmount" required>
                        </div>
                        <div class="form-group">
                            <label for="editBankname">银行名</label>
                            <input type="text" class="form-control" name="bankname" id="editBankname" required>
                        </div>
                        <div class="form-group">
                            <label for="editAccname">账户名</label>
                            <input type="text" class="form-control" name="accname" id="editAccname" required>
                        </div>
                        <div class="form-group">
                            <label for="editAccno">账户号</label>
                            <input type="text" class="form-control" name="accno" id="editAccno" required>
                        </div>
                        <div class="form-group">
                            <label for="editBranch">支行号</label>
                            <input type="text" class="form-control" name="branch" id="editBranch" required>
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
        <script src="{{ asset('plugins/global/vendors.min.js') }}"></script>
        @vite(['resources/assets/js/custom.js'])
        <script src="{{ asset('plugins/table/datatable/datatables.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('#withdrawals-table').DataTable({
                    "dom": "<'dt--top-section'<'row'<'col-12 col-sm-6 d-flex justify-content-sm-start justify-content-center'l><'col-12 col-sm-6 d-flex justify-content-sm-end justify-content-center mt-sm-0 mt-3'f>>>" +
                           "<'table-responsive'tr>" +
                           "<'dt--bottom-section d-sm-flex justify-content-between text-center'<'dt--pages-count mb-sm-0 mb-3'i><'dt--pagination'p>>",
                    "oLanguage": {
                        "oPaginate": {
                            "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                            "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                        },
                        "sInfo": "显示第 _PAGE_ 页，共 _PAGES_ 页",
                        "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                        "sSearchPlaceholder": "搜索...",
                        "sLengthMenu": "结果数:  _MENU_",
                    },
                    "stripeClasses": [],
                    "lengthMenu": [7, 10, 20, 50],
                    "pageLength": 10
                });
            });
        </script>
        <script>
            $(document).ready(function () {
                function handleWithdrawalAction(txid, action, additionalData = {}) {
                    let url = `/app/withdrawals/${action}`;
                    let data = {
                        _token: '{{ csrf_token() }}',
                        txid: txid,
                        ...additionalData
                    };
                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: data,
                        success: function (response) {
                            alert(response.message);
                            location.reload();
                        },
                        error: function (xhr) {
                            alert(xhr.responseJSON.message || '发生错误，请重试。');
                        }
                    });
                }

                $('.approve-withdrawal').on('click', function () {
                    const txid = $(this).data('txid');
                    if (confirm('您确定要批准此提款吗？')) {
                        handleWithdrawalAction(txid, 'approve');
                    }
                });

                // Handle Reject Button to open Modal
                $('.reject-withdrawal').on('click', function () {
                    const txid = $(this).data('txid');
                    $('#rejectTxid').val(txid);
                    $('#rejectReason').val('');
                    $('#rejectModal').modal('show');
                });

                // Handle Rejection Form Submission
                $('#rejectForm').submit(function (e) {
                    e.preventDefault();

                    const formData = $(this).serialize();

                    $.ajax({
                        url: '/app/withdrawals/reject',
                        method: 'POST',
                        data: formData,
                        success: function (response) {
                            alert(response.message);
                            $('#rejectModal').modal('hide');
                            location.reload();
                        },
                        error: function (xhr) {
                            alert(xhr.responseJSON.message || '发生错误，请重试。');
                        }
                    });
                });

                // Handle Edit Button to open Edit Modal
                $('.edit-withdrawal').on('click', function () {
                    const txid = $(this).data('txid');
                    const amount = $(this).data('amount');
                    const bankname = $(this).data('bankname');
                    const accname = $(this).data('accname');
                    const accno = $(this).data('accno');
                    const branch = $(this).data('branch');

                    $('#editTxid').val(txid);
                    $('#editAmount').val(amount);
                    $('#editBankname').val(bankname);
                    $('#editAccname').val(accname);
                    $('#editAccno').val(accno);
                    $('#editBranch').val(branch);

                    $('#editModal').modal('show');
                });

                // Handle Edit Form Submission
                $('#editForm').submit(function (e) {
                    e.preventDefault();

                    const formData = $(this).serialize();

                    $.ajax({
                        url: '/app/withdrawals/update',
                        method: 'POST',
                        data: formData,
                        success: function (response) {
                            alert(response.message);
                            $('#editModal').modal('hide');
                            location.reload();
                        },
                        error: function (xhr) {
                            alert(xhr.responseJSON.message || '发生错误，请重试。');
                        }
                    });
                });
            });
        </script>
    </x-slot>
</x-base-layout>