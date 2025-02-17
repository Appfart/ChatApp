{{-- 
    
/**
*
* Created a new component <x-rtl.widgets._w-three/>.
* 
*/

--}}

<div class="widget widget-three">
    <div class="widget-heading">
        <h5 class="">{{ $title }}</h5>

        <div class="task-action">
            <div class="dropdown">
                <a class="dropdown-toggle" href="#" role="button" id="summary" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-more-horizontal">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="19" cy="12" r="1"></circle>
                        <circle cx="5" cy="12" r="1"></circle>
                    </svg>
                </a>

                <div class="dropdown-menu left" aria-labelledby="summary" style="will-change: transform;">
                    <a class="dropdown-item" href="javascript:void(0);">View Report</a>
                    <a class="dropdown-item" href="javascript:void(0);">Edit Report</a>
                    <a class="dropdown-item" href="javascript:void(0);">Mark as Done</a>
                </div>
            </div>
        </div>

    </div>
    <div class="widget-content">

        <div class="order-summary">

            <!-- Total Deposit Summary -->
            <div class="summary-list">
                <div class="w-icon">
                    <!-- Deposit Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-credit-card">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                </div>
                <div class="w-summary-details">
                    
                    <div class="w-summary-info">
                        <h6>Total Deposit</h6>
                        <p class="summary-count">$120,450</p>
                    </div>

                    <div class="w-summary-stats">
                        <div class="progress">
                            <div class="progress-bar bg-gradient-primary" role="progressbar" 
                                 style="width: 85%" aria-valuenow="85" aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Total Freeze Summary -->
            <div class="summary-list">
                <div class="w-icon">
                    <!-- Freeze Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-lock">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div class="w-summary-details">
                    
                    <div class="w-summary-info">
                        <h6>Total Freeze</h6>
                        <p class="summary-count">$45,300</p>
                    </div>

                    <div class="w-summary-stats">
                        <div class="progress">
                            <div class="progress-bar bg-gradient-info" role="progressbar" 
                                 style="width: 60%" aria-valuenow="60" aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                    </div>

                </div>

            </div>

            <!-- Total Release Summary -->
            <div class="summary-list">
                <div class="w-icon">
                    <!-- Release Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                         viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" 
                         class="feather feather-unlock">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M3 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <div class="w-summary-details">
                    
                    <div class="w-summary-info">
                        <h6>Total Release</h6>
                        <p class="summary-count">$75,150</p>
                    </div>

                    <div class="w-summary-stats">
                        <div class="progress">
                            <div class="progress-bar bg-gradient-success" role="progressbar" 
                                 style="width: 75%" aria-valuenow="75" aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                    </div>

                </div>

            </div>
            
        </div>
        
    </div>
</div>
