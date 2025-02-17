<div class="container mt-3">
    <h5 class="mb-4">好友列表</h5>

    <!-- Search Bar -->
    <div class="mb-3">
        <input type="text" id="search-friend" class="form-control" placeholder="输入用户名或者推荐码" />
    </div>

    <!-- Search Results -->
    <div id="search-results" class="d-none">
        <h6>搜索结果</h6>
        <ul id="results-list" class="list-group mb-3"></ul>
    </div>

    <!-- Friend List -->
    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table class="table table-bordered table-hover align-middle">
            <thead class="thead-dark">
                <tr>
                    <th>用户名</th>
                    <th>姓名</th>
                    <th>年纪</th>
                    <th>推荐码</th>
                    <th>钱包余额</th>
                    <th>冻结余额</th>
                    <th style="text-align: center;">开干</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($friends as $friend)
                    <tr>
                        <td>{{ $friend['name'] }}</td>
                        <td>{{ $friend['realname'] ?? 'N/A' }}</td>
                        <td>{{ $friend['age'] ?? 'N/A' }}</td>
                        <td>{{ $friend['referral_link'] }}</td>
                        <td>{{ number_format($friend['wallet_balance'], 2) }}</td>
                        <td>{{ number_format($friend['wallet_freeze'], 2) }}</td>
                        <td style="text-align: center;">
                            {{--<button class="btn btn-primary btn-sm" onclick="startChat({{ $friend['id'] }})">Chat</button>--}}
                            <button class="btn btn-danger btn-sm" onclick="removeFriend({{ $friend['id'] }})">拉黑</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No friends found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const impersonationToken = '{{ request()->get('impersonation_token') }}';

    $('#search-friend').on('input', function () {
        const query = $(this).val().trim();

        if (query.length === 0) {
            $('#search-results').addClass('d-none'); // Hide results
            return;
        }

        $.ajax({
            url: '/robot/search-friend',
            method: 'POST',
            data: {
                query: query,
                impersonation_token: impersonationToken, // Include impersonation token
                _token: '{{ csrf_token() }}',
            },
            success: function (response) {
                if (response.status === 'success') {
                    const results = response.friends;

                    if (results && results.length > 0) {
                        $('#results-list').empty();

                        results.forEach(friend => {
                            const name = escapeHTML(friend.name || 'Unknown');
                            const referralLink = escapeHTML(friend.referral_link || 'N/A');
                            let actionButton;

                            // Determine button text based on status
                            if (friend.status === 2) {
                                actionButton = `<span class="badge badge-success">已加好友</span>`;
                            } else if (friend.status === 1) {
                                actionButton = `<span class="badge badge-warning">等待接受</span>`;
                            } else {
                                actionButton = `<button class="btn btn-sm btn-success" onclick="addFriend('${referralLink}')">加好友</button>`;
                            }

                            $('#results-list').append(`
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>${name} (${referralLink})</span>
                                    ${actionButton}
                                </li>
                            `);
                        });

                        $('#search-results').removeClass('d-none');
                    } else {
                        $('#results-list').html('<li class="list-group-item text-muted">No results found.</li>');
                    }
                } else {
                    alert(response.message);
                }
            },
            error: function (xhr) {
                console.error('Search failed:', xhr.responseJSON?.message || 'Unknown error occurred.');
            }
        });
    });

    // Escape HTML to prevent injection
    function escapeHTML(str) {
        return String(str).replace(/[&<>"'`=\/]/g, function (s) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '=': '&#x3D;',
                '`': '&#x60;'
            })[s];
        });
    }

    // Add Friend Request
    window.addFriend = function (referralLink) {
        $.ajax({
            url: '/robot/add-friend',
            method: 'POST',
            data: {
                referral_link: referralLink,
                impersonation_token: impersonationToken, // Include impersonation token
                _token: '{{ csrf_token() }}',
            },
            success: function (response) {
                alert(response.message);
                $('#search-friend').trigger('input'); // Refresh search results
            },
            error: function (xhr) {
                alert(xhr.responseJSON?.message || 'Failed to send friend request.');
            }
        });
    };
});

</script>
