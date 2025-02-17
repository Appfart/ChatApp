<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>机器人联机</x-slot>

    <x-slot:headerFiles>
        <link rel="stylesheet" href="{{ asset('plugins/drag-and-drop/dragula/dragula.css') }}">
        <!-- Toastr CSS for notifications (optional) -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
        <style>
            /* Add styles to make nested lists visible and properly formatted */
            .nested-list {
                min-height: 50px;
                border: 1px dashed #ccc;
                padding: 10px;
                margin-top: 10px;
                background-color: #f9f9f9;
            }
            .nested-list.empty::before {
                content: 'Drag robots here';
                color: #aaa;
                display: block;
                text-align: center;
                margin-top: 15px;
            }
            .robot-item, .support-item {
                cursor: move;
            }
        </style>
    </x-slot>

    <div class="row" style="margin-top:20px; height: 850px;">
        <!-- Customer Service Staff -->
        <div class="col-md-6" style="height:100%; overflow: scroll;">
            <h5>客服内部人员</h5>
            <ul class="list-group" id="supports-list">
                @foreach ($supports as $support)
                    <li class="list-group-item support-item" data-id="{{ $support->id }}">
                        <div>
                            <strong>{{ $support->name }}</strong>
                            <span class="badge bg-primary" data-support-id="{{ $support->id }}">
                                {{ $support->supportedRobots->where('status', 1)->count() }} 机器人
                            </span>
                            <p>姓名: {{ $support->realname ?? 'N/A' }} --> 推荐码: {{ $support->referral_link ?? 'N/A' }}</p>
                        </div>
                        <ul class="nested-list" id="support-{{ $support->id }}">
                            @foreach ($support->supportedRobots->where('status', 1) as $link)
                                <li class="list-group-item robot-item d-flex align-items-center justify-content-between" data-id="{{ $link->robot->id }}">
                                    <div class="d-flex align-items-center">
                                        @if ($link->robot->avatar)
                                            <img src="{{ asset('storage/'.$link->robot->avatar) }}" alt="Avatar" class="rounded-circle" style="width:40px; height:40px; object-fit: cover; margin-right: 10px;">
                                        @else
                                            <div class="avatar-placeholder rounded-circle" style="width:40px; height:40px; background-color:#ccc; color:#fff; display:flex; align-items:center; justify-content:center; margin-right:10px;">
                                                <span>{{ strtoupper(substr($link->robot->name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <strong>{{ $link->robot->name }}</strong>
                                            <p>姓名: {{ $link->robot->realname ?? 'N/A' }} --> 推荐码: {{ $link->robot->referral_link ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#updateAvatarModal" data-robot-id="{{ $link->robot->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-settings">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                        </svg>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        
                        <!-- Modal for Updating Avatar -->
                        <div class="modal fade" id="updateAvatarModal" tabindex="-1" aria-labelledby="updateAvatarModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content bg-white">
                                    <form id="updateAvatarForm" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="updateAvatarModalLabel">更新头像</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="robot_id" id="robotIdInput">
                                            <div class="mb-3">
                                                <label for="avatar" class="form-label">上传新头像</label>
                                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                            <button type="submit" class="btn btn-primary">更新头像</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    
        <!-- Robot Client List -->
        <div class="col-md-6" style="height:100%; overflow: scroll;">
            <h5>机器人客户列表</h5>
            <ul class="list-group" id="robots-list" style="height:100%; overflow: auto;">
                @foreach ($robots as $robot)
                    <li class="list-group-item robot-item d-flex align-items-center justify-content-between" data-id="{{ $robot->id }}">
                        <div class="d-flex align-items-center">
                            @if ($robot->avatar)
                                <img src="{{ asset('storage/'.$robot->avatar) }}" alt="Avatar" class="rounded-circle" style="width:40px; height:40px; object-fit: cover; margin-right: 10px;">
                            @else
                                <div class="avatar-placeholder rounded-circle" style="width:40px; height:40px; background-color:#ccc; color:#fff; display:flex; align-items:center; justify-content:center; margin-right:10px;">
                                    <span>{{ strtoupper(substr($robot->name, 0, 1)) }}</span>
                                </div>
                            @endif
                            <div>
                                <strong>{{ $robot->name }}</strong> 
                                <p>姓名: {{ $robot->realname ?? 'N/A' }} --> 推荐码: {{ $robot->referral_link ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#updateAvatarModal" data-robot-id="{{ $robot->id }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-settings">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>


    <x-slot:footerFiles>
        <script src="{{ asset('plugins/drag-and-drop/dragula/dragula.min.js') }}"></script>
        <!-- Toastr JS for notifications (optional) -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
        
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                console.log('Drag-and-drop script initialized');

                const supportsLists = document.querySelectorAll('.nested-list');
                const robotsList = document.getElementById('robots-list');

                if (!robotsList) {
                    console.error('Error: Robots list container not found!');
                    return;
                } else {
                    console.log('Robots list initialized:', robotsList);
                }

                if (supportsLists.length === 0) {
                    console.error('Error: Supports list containers not found!');
                    return;
                } else {
                    console.log('Supports lists initialized:', supportsLists);
                }

                // Initialize dragula with moving instead of copying
                const drake = dragula([...supportsLists, robotsList], {
                    removeOnSpill: false,
                    copy: false, // Disable copying to ensure moving
                    accepts: function (el, target) {
                        // Allow robots to be dropped into supports' nested lists or back to robotsList
                        return target.classList.contains('nested-list') || target.id === 'robots-list';
                    },
                    moves: function (el, source, handle, sibling) {
                        // Only allow dragging robot items
                        return el.classList.contains('robot-item');
                    }
                });

                drake.on('drag', function (el) {
                    console.log('Dragging:', el);
                });

                drake.on('drop', function (el, target, source, sibling) {
                    const robotId = el.dataset.id;
                
                    if (!target) {
                        drake.cancel(true);
                        return;
                    }
                
                    if (target.classList.contains('nested-list')) {
                        // Assign robot to support
                        const supportId = target.closest('.support-item').dataset.id;
                
                        fetch('{{ route('lobby.assign-robots') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                support_id: supportId,
                                robot_ids: [robotId],
                            }),
                        })
                        .then(response => response.json().then(data => ({ httpStatus: response.status, body: data })))
                        .then(({ httpStatus, body }) => {
                            console.log('HTTP Status:', httpStatus);
                            console.log('Response body:', body);
                
                            if (httpStatus === 200) {
                                // Wrap toastr call in try-catch
                                try {
                                    toastr.success(body.message || 'Robot assigned successfully.');
                                } catch (err) {
                                    console.error('Toastr success error:', err);
                                }
                
                                // Update the robot count
                                const badge = document.querySelector(`.badge[data-support-id="${supportId}"]`);
                                console.log('Badge element:', badge);
                                console.log('New robot count from server:', body.newRobotCount);
                
                                if (badge && typeof body.newRobotCount !== 'undefined') {
                                    badge.textContent = `${body.newRobotCount} 机器人`;
                                    console.log('Badge text updated to:', badge.textContent);
                                } else {
                                    console.warn('Badge element not found or newRobotCount is undefined');
                                }
                            } else {
                                console.error('Server error:', body.message);
                                // Wrap toastr call in try-catch
                                try {
                                    toastr.error(body?.message || 'Error assigning robot.');
                                } catch (err) {
                                    console.error('Toastr error:', err);
                                }
                                drake.cancel(true);
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            // Wrap toastr call in try-catch
                            try {
                                toastr.error('An error occurred while assigning the robot.');
                            } catch (err) {
                                console.error('Toastr error:', err);
                            }
                            drake.cancel(true);
                        });
                    } else if (target.id === 'robots-list') {
                        // Detach robot from support
                        const supportId = source.closest('.support-item').dataset.id;
                
                        fetch('{{ route('lobby.detach-robot') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                support_id: supportId,
                                robot_id: robotId,
                            }),
                        })
                        .then(response => response.json().then(data => ({ httpStatus: response.status, body: data })))
                        .then(({ httpStatus, body }) => {
                            console.log('HTTP Status:', httpStatus);
                            console.log('Response body:', body);
                
                            if (httpStatus === 200) {
                                // Wrap toastr call in try-catch
                                try {
                                    toastr.success(body.message || 'Robot detached successfully.');
                                } catch (err) {
                                    console.error('Toastr success error:', err);
                                }
                
                                // Update the robot count
                                const badge = document.querySelector(`.badge[data-support-id="${supportId}"]`);
                                console.log('Badge element:', badge);
                                console.log('New robot count from server:', body.newRobotCount);
                
                                if (badge && typeof body.newRobotCount !== 'undefined') {
                                    badge.textContent = `${body.newRobotCount} 机器人`;
                                    console.log('Badge text updated to:', badge.textContent);
                                } else {
                                    console.warn('Badge element not found or newRobotCount is undefined');
                                }
                            } else {
                                console.error('Server error:', body.message);
                                // Wrap toastr call in try-catch
                                try {
                                    toastr.error(body?.message || 'Error detaching robot.');
                                } catch (err) {
                                    console.error('Toastr error:', err);
                                }
                                drake.cancel(true);
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            // Wrap toastr call in try-catch
                            try {
                                toastr.error('An error occurred while detaching the robot.');
                            } catch (err) {
                                console.error('Toastr error:', err);
                            }
                            drake.cancel(true);
                        });
                    } else {
                        drake.cancel(true);
                    }
                });

                drake.on('cancel', function (el, container, source) {
                    console.log('Drag canceled for:', el);
                });

            });
        </script>
        
        <!-- Update Avatar -->
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const updateAvatarModal = document.getElementById('updateAvatarModal');
            const robotIdInput = document.getElementById('robotIdInput');
    
            updateAvatarModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; // Button that triggered the modal
                const robotId = button.getAttribute('data-robot-id'); // Extract info from data-* attributes
                robotIdInput.value = robotId;
            });
    
            document.getElementById('updateAvatarForm').addEventListener('submit', function (e) {
                e.preventDefault();
    
                const formData = new FormData(this);
    
                fetch('{{ route('user.update-robot') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: formData,
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('头像更新成功！');
                            location.reload();
                        } else {
                            alert('头像更新失败: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('更新头像时发生错误');
                    });
            });
        });
    </script>
    </x-slot>
</x-base-layout>
