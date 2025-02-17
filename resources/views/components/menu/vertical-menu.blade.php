{{-- 

/**
*
* Created a new component <x-menu.vertical-menu/>.
* 
*/

--}}

    
<div class="sidebar-wrapper sidebar-theme">

<nav id="sidebar">

    <div class="navbar-nav theme-brand flex-row  text-center">
        <div class="nav-logo">
            <div class="nav-item theme-logo">
                <a href="{{getRouterValue();}}/dashboard">
                    <img src="/default-avatar.png" class="navbar-logo logo-dark" alt="logo">
                    <img src="/default-avatar.png" class="navbar-logo logo-light" alt="logo">
                </a>
            </div>
            <div class="nav-item theme-text">
                <a href="{{getRouterValue();}}/dashboard/analytics" class="nav-link">民生小康</a>
            </div>
        </div>
        <div class="nav-item sidebar-toggle">
            <div class="btn-toggle sidebarCollapse">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevrons-left"><polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline></svg>
            </div>
        </div>
    </div>
    @if (!Request::is('collapsible-menu/*'))
        <div class="profile-info">
            <div class="user-info">
                <div class="profile-img">
                    <img 
                        src="{{ Auth::user()->avatar ? asset('storage/' . Auth::user()->avatar) : Vite::asset('resources/images/profile-30.png') }}" 
                        alt="avatar" 
                        class="rounded-circle">

                </div>
                <div class="profile-content">
                    <h6>{{ Auth::user()->realname }}</h6>
                    <p>{{ Auth::user()->role }}</p>
                </div>
            </div>
        </div>
    @endif
    <div class="shadow-bottom"></div>
    <ul class="list-unstyled menu-categories" id="accordionExample">
        <li class="menu {{ Request::routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" aria-expanded="false" class="dropdown-toggle">
                <div class="">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-home"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span>主控室</span>
                </div>
            </a>
        </li>

        <li class="menu menu-heading">
            <div class="heading"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-minus"><line x1="5" y1="12" x2="19" y2="12"></line></svg><span>工具箱</span></div>
        </li>
        
        <li class="menu {{ Request::routeIs('tickets.index') ? 'active' : '' }}">
            <a href="{{ route('tickets.index') }}" aria-expanded="false" class="dropdown-toggle">
                <div class="d-flex align-items-center">
                    <!-- Ticket Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-message-square">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <!-- Menu Text -->
                    <span class="ms-2">客服單號&nbsp;</span>
                    <!-- Notification Badge -->
                    <span class="badge bg-danger fs-6 px-2 py-1 ms-auto" id="pending-ticket-count">
                        {{ $pendingTicketCount ?? 0 }}
                    </span>
                </div>
            </a>
        </li>
        
        @if (Auth::user()->role === 'superadmin')
        <li class="menu {{ Request::routeIs('batch.create.form') ? 'active' : '' }}">
            <a href="{{ route('batch.create.form') }}" aria-expanded="false" class="dropdown-toggle">
                <div class="">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-square">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span>腳本【机器戶】</span>
                </div>
            </a>
        </li>
        <li class="menu {{ Request::routeIs('user.create') ? 'active' : '' }}">
            <a href="{{ route('user.create') }}" aria-expanded="false" class="dropdown-toggle">
                <div class="">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-square">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span>腳本【业务员】</span>
                </div>
            </a>
        </li>
        @endif

        <!-- 机器人大厅 -->
        <li class="menu {{ Request::routeIs('lobby.*') ? 'active' : '' }}">
            <a href="#robotsSubmenu" data-bs-toggle="collapse" aria-expanded="{{ Request::routeIs('lobby.*') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Users Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-layers">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    <span>机器控台</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('lobby.index') ? 'show' : '' }}" id="robotsSubmenu" data-bs-parent="#accordionExample">
                
                @if (Auth::user()->role === 'superadmin')
                <li class="{{ Request::routeIs('lobby.index') ? 'active' : '' }}">
                    <a href="{{ route('lobby.index') }}"> 机器人联机</a>
                </li>
                @endif
                
                <li class="{{ Request::routeIs('lobby.robotchat') ? 'active' : '' }}">
                    <a href="{{ route('lobby.robotchat') }}"> 登入名单</a>
                </li>
            </ul>
        </li>

        
        <li class="menu menu-heading">
            <div class="heading">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-minus">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>用戶管理</span>
            </div>
        </li>
        
        <li class="menu {{ Request::routeIs('user.list') ? 'active' : '' }}">
            <a href="#usersSubmenu1" data-bs-toggle="collapse" 
               aria-expanded="{{ Request::routeIs('user.list') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Users Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-layers">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    <span>用戶管理</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('user.list') ? 'show' : '' }}" 
                id="usersSubmenu1" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('user.list') ? 'active' : '' }}">
                    <a href="{{ route('user.list') }}"> 用戶列表</a>
                </li>
            </ul>
        </li>
        
        <li class="menu {{ Request::routeIs('chathistory') ? 'active' : '' }}">
            <a href="#usersSubmenu2" data-bs-toggle="collapse" 
               aria-expanded="{{ Request::routeIs('chathistory') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Chat History Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-message-circle">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M8 9h8M8 13h4"></path>
                    </svg>
                    <span>对话记录</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('chathistory') ? 'show' : '' }}" 
                id="usersSubmenu2" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('chathistory') ? 'active' : '' }}">
                    <a href="{{ route('chathistory') }}"> 对话列表</a>
                </li>
            </ul>
        </li>
        
        <!-- Wallet Menu -->
        @if (Auth::user()->role === 'superadmin')
        <li class="menu {{ Request::routeIs('wallet.action') ? 'active' : '' }}">
            <a href="#walletActionsSubmenu" data-bs-toggle="collapse" aria-expanded="{{ Request::routeIs('wallet.action') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Wallet Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-wallet">
                        <path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4"></path>
                        <path d="M21 10V14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10"></path>
                        <path d="M16 6v4"></path>
                        <path d="M8 6v4"></path>
                    </svg>
                    <span>錢包管理</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('wallet.action') ? 'show' : '' }}" id="walletActionsSubmenu" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('wallet.action') ? 'active' : '' }}">
                    <a href="{{ route('wallet.action') }}"> 錢包控台 </a>
                </li>
            </ul>
        </li>
        
        <!-- Transactions -->
        <li class="menu {{ Request::routeIs('transaction.index') ? 'active' : '' }}">
            <a href="#TranSubmenu" data-bs-toggle="collapse" aria-expanded="{{ Request::routeIs('transaction.index') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Wallet Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-wallet">
                        <path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4"></path>
                        <path d="M21 10V14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10"></path>
                        <path d="M16 6v4"></path>
                        <path d="M8 6v4"></path>
                    </svg>
                    <span>用户明细</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('transaction.index') ? 'show' : '' }}" id="TranSubmenu" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('transaction.index') ? 'active' : '' }}">
                    <a href="{{ route('transaction.index') }}"> 流水明细 </a>
                </li>
            </ul>
        </li>
        
        <!-- Deposit Menu -->
        <li class="menu {{ Request::routeIs('deposits.index') ? 'active' : '' }}">
            <a href="#depositSubmenu" data-bs-toggle="collapse" aria-expanded="{{ Request::routeIs('deposits.index') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Deposit Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-wallet">
                        <path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4"></path>
                        <path d="M21 10V14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10"></path>
                        <path d="M16 6v4"></path>
                        <path d="M8 6v4"></path>
                    </svg>
                    <span>充值</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('deposits.index') ? 'show' : '' }}" id="depositSubmenu" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('deposits.index') ? 'active' : '' }}">
                    <a href="{{ route('deposits.index') }}"> 充值列表 </a>
                </li>
            </ul>
        </li>
        
        <!-- Deposit Menu -->
        <li class="menu {{ Request::routeIs('withdrawals.index') ? 'active' : '' }}">
            <a href="#withdrawalSubmenu" data-bs-toggle="collapse" aria-expanded="{{ Request::routeIs('withdrawals.index') ? 'true' : 'false' }}" class="dropdown-toggle">
                <div class="">
                    <!-- Deposit Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-wallet">
                        <path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4"></path>
                        <path d="M21 10V14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10"></path>
                        <path d="M16 6v4"></path>
                        <path d="M8 6v4"></path>
                    </svg>
                    <span>提现</span>
                </div>
                <div>
                    <!-- Chevron Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" 
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::routeIs('withdrawals.index') ? 'show' : '' }}" id="withdrawalSubmenu" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('withdrawals.index') ? 'active' : '' }}">
                    <a href="{{ route('withdrawals.index') }}"> 提现列表 </a>
                </li>
            </ul>
        </li>
        @endif

        <li class="menu menu-heading">
            <div class="heading"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-minus"><line x1="5" y1="12" x2="19" y2="12"></line></svg><span>個人帳號</span></div>
        </li>                    

        <li class="menu {{ Request::routeIs('user.profile') ? 'active' : '' }}">
            <a href="#users" data-bs-toggle="collapse" aria-expanded="{{ Request::routeIs('user.profile') ? 'active' : '' }}" class="dropdown-toggle">
                <div class="">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-users">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>帳號管理</span>
                </div>
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevron-right">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </a>
            <ul class="collapse submenu list-unstyled {{ Request::is('user.profile') ? 'show' : '' }}" id="users" data-bs-parent="#accordionExample">
                <li class="{{ Request::routeIs('user.profile') ? 'active' : '' }}">
                    <a href="{{ url('/app/user/profile') }}"> 用戶資料 </a>
                </li>
                <li class="{{ Request::routeIs('user.settings') ? 'active' : '' }}">
                    <a href="{{ url('/app/user/settings') }}"> 戶口設置 </a>
                </li>
            </ul>
        </li>

    </ul>
    
</nav>

</div>

<!-- Include Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://js.pusher.com/7.2/pusher.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/laravel-echo/1.12.1/echo.iife.js"></script>
<script src="{{ asset('js/ticket.js') }}"></script>

<script>
    var currentUserRealName = "{{ auth()->user()->realname }}";
    window.PUSHER_APP_KEY = "{{ env('PUSHER_APP_KEY') }}";
    window.PUSHER_APP_CLUSTER = "{{ env('PUSHER_APP_CLUSTER') }}";
    window.USER_ROLE = "{{ Auth::user()->role }}";
</script>