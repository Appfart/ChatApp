<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        编辑用户详情
    </x-slot>

    <x-slot:headerFiles>
        @vite(['resources/scss/light/plugins/table/datatable/dt-global_style.scss'])
        @vite(['resources/scss/dark/plugins/table/datatable/dt-global_style.scss'])
    </x-slot>

    <div class="row mt-4">
        <div class="col-12">
            <!-- Display Success and Error Messages -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <!-- User Details Form -->
            <form method="POST" action="{{ route('user.updateUser', $user->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <h4>用户详情</h4>
                
                <!-- Profile Picture Section -->
                <div class="mb-3">
                     @if ($user->avatar)
                        <div class="mb-2 mt-3">
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="Profile Picture" width="150" height="150" class="rounded-circle">
                        </div>
                    @endif
                    <label for="avatar" class="form-label mt-3">个人头像</label>
                    <input type="file" name="avatar" class="form-control">
                   
                </div>


                <!-- 第一组 -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">用户名</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="realname" class="form-label">真实姓名</label>
                        <input type="text" name="realname" value="{{ old('realname', $user->realname) }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="age" class="form-label">年龄</label>
                        <input type="number" name="age" value="{{ old('age', $user->age) }}" class="form-control">
                    </div>
                </div>

                <!-- 第二组 -->
                <div class="row g-3 mt-4">
                    <div class="col-md-6">
                        <label for="referral_link" class="form-label">推荐链接</label>
                        <input type="text" name="referral_link" value="{{ old('referral_link', $user->referral_link) }}" class="form-control" placeholder="输入推荐链接">
                    </div>

                    <div class="col-md-6">
                        <label for="security_pin" class="form-label">安全 PIN</label>
                        <input type="text" name="security_pin" value="{{ old('security_pin', $user->security_pin) }}" class="form-control" maxlength="4" placeholder="输入4位PIN码">
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">新密码</label>
                        <input type="password" name="password" class="form-control" placeholder="输入新密码">
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">确认密码</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="确认新密码">
                    </div>
                </div>

                <!-- 保存用户详情按钮 -->
                <div class="col-md-12 mt-4 d-flex justify-content-start">
                    <button type="submit" class="btn btn-primary me-2">保存用户详情</button>
                </div>
            </form>

            <hr class="my-5">

            <!-- Bank Details Form -->
            <form method="POST" action="{{ route('user.updateBank', $user->id) }}">
                @csrf
                @method('PUT')

                <h4>银行信息</h4>
                <div id="banks-wrapper">
                    @php
                        $banks = old('banks', $user->userbanks->toArray());
                    @endphp
                    @foreach($banks as $index => $bank)
                        <div class="bank-entry border p-3 mb-3">
                            @if(isset($bank['id']))
                                <input type="hidden" name="banks[{{ $index }}][id]" value="{{ $bank['id'] }}">
                            @endif
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="banks[{{ $index }}][bankname]" class="form-label">银行名称</label>
                                    <input type="text" name="banks[{{ $index }}][bankname]" value="{{ $bank['bankname'] }}" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="banks[{{ $index }}][accname]" class="form-label">账户名</label>
                                    <input type="text" name="banks[{{ $index }}][accname]" value="{{ $bank['accname'] }}" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="banks[{{ $index }}][bankno]" class="form-label">银行账号</label>
                                    <input type="text" name="banks[{{ $index }}][bankno]" value="{{ $bank['bankno'] }}" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="banks[{{ $index }}][branch]" class="form-label">支行 (可选)</label>
                                    <input type="text" name="banks[{{ $index }}][branch]" value="{{ $bank['branch'] }}" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="banks[{{ $index }}][iban]" class="form-label">IBAN (可选)</label>
                                    <input type="text" name="banks[{{ $index }}][iban]" value="{{ $bank['iban'] }}" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="banks[{{ $index }}][status]" class="form-label">状态</label>
                                    <select name="banks[{{ $index }}][status]" class="form-control" required>
                                        <option value="1" {{ $bank['status'] == 1 ? 'selected' : '' }}>激活</option>
                                        <option value="0" {{ $bank['status'] == 0 ? 'selected' : '' }}>未激活</option>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger mt-3 remove-bank">删除银行</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-secondary" id="add-bank">添加银行</button>

                <!-- 保存银行详情按钮 -->
                <div class="col-md-12 mt-4 d-flex justify-content-start">
                    <button type="submit" class="btn btn-primary me-2">保存银行详情</button>
                </div>
            </form>

            <hr class="my-5">

            <!-- Wallet Details Form -->
            <form method="POST" action="{{ route('user.updateWallet', $user->id) }}">
                @csrf
                @method('PUT')

                <h4>钱包信息</h4>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="wallet_amount" class="form-label">钱包金额</label>
                        <input type="number" name="wallet_amount" value="{{ old('wallet_amount', $user->wallet->amount ?? 0) }}" class="form-control" step="0.01" placeholder="输入钱包金额">
                    </div>
                    <div class="col-md-6">
                        <label for="wallet_freeze" class="form-label">冻结金额</label>
                        <input type="number" name="wallet_freeze" value="{{ old('wallet_freeze', $user->wallet->freeze ?? 0) }}" class="form-control" step="0.01" placeholder="输入冻结金额">
                    </div>
                    <div class="col-md-6">
                        <label for="wallet_status" class="form-label">显示开关状态</label>
                        <select name="wallet_status" class="form-control">
                            <option value="1" {{ old('wallet_status', $user->wallet->status ?? 0) == 1 ? 'selected' : '' }}>开启</option>
                            <option value="0" {{ old('wallet_status', $user->wallet->status ?? 0) == 0 ? 'selected' : '' }}>关闭</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="wallet_type" class="form-label">钱包状态显示</label>
                        <input type="text" name="wallet_type" value="{{ old('wallet_type', $user->wallet->type ?? '') }}" class="form-control" placeholder="显示钱包状态">
                    </div>
                </div>

                <!-- 保存钱包详情按钮 -->
                <div class="col-md-12 mt-4 d-flex justify-content-start">
                    <button type="submit" class="btn btn-primary me-2">保存钱包详情</button>
                </div>
            </form>
        </div>
    </div>

    <x-slot:footerFiles>
        @vite(['resources/assets/js/custom.js'])

        <!-- Include JavaScript for Dynamic Bank Entries -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let bankIndex = {{ count($banks) }};

                function updateAddBankButtonState() {
                    const banksWrapper = document.getElementById('banks-wrapper');
                    const addBankButton = document.getElementById('add-bank');
                    const existingBanks = banksWrapper.querySelectorAll('.bank-entry');
                    // Allow adding only if no existing bank entries (adjust based on your requirements)
                    addBankButton.disabled = false; // Remove or adjust condition as needed
                }

                document.getElementById('add-bank').addEventListener('click', function () {
                    const banksWrapper = document.getElementById('banks-wrapper');
                    const bankEntry = document.createElement('div');
                    bankEntry.classList.add('bank-entry', 'border', 'p-3', 'mb-3');
                    bankEntry.innerHTML = `
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">银行名称</label>
                                <input type="text" name="banks[${bankIndex}][bankname]" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">账户名</label>
                                <input type="text" name="banks[${bankIndex}][accname]" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">银行账号</label>
                                <input type="text" name="banks[${bankIndex}][bankno]" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">支行 (可选)</label>
                                <input type="text" name="banks[${bankIndex}][branch]" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IBAN (可选)</label>
                                <input type="text" name="banks[${bankIndex}][iban]" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">状态</label>
                                <select name="banks[${bankIndex}][status]" class="form-control" required>
                                    <option value="1">激活</option>
                                    <option value="0">未激活</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger mt-3 remove-bank">删除银行</button>
                    `;
                    banksWrapper.appendChild(bankEntry);
                    bankIndex++;
                    updateAddBankButtonState();
                });

                document.getElementById('banks-wrapper').addEventListener('click', function (e) {
                    if (e.target && e.target.classList.contains('remove-bank')) {
                        e.target.closest('.bank-entry').remove();
                        updateAddBankButtonState();
                    }
                });

                updateAddBankButtonState();
            });
        </script>
    </x-slot>
</x-base-layout>
