{{-- 
/**
*
* Created a new component <x-rtl.widgets._w-activity-four/>.
* 
*/
--}}

<div class="widget widget-activity-four">
    
    <div class="widget-heading">
        <h5 class="">{{$title}}</h5>
    </div>

    <div class="widget-content">

        <div class="mt-container-ra mx-auto">
            <div class="timeline-line">

                <!-- User Login Activity -->
                <div class="item-timeline timeline-success">
                    <div class="t-dot" title="User Login">
                    </div>
                    <div class="t-text">
                        <p>User <span>John Doe</span> logged in from <span>IP 175.137.80.43</span></p>
                        <span class="badge">Success</span>
                        <p class="t-time">08:05 AM</p>
                    </div>
                </div>

                <!-- User Message Sent -->
                <div class="item-timeline timeline-primary">
                    <div class="t-dot" title="Message Sent">
                    </div>
                    <div class="t-text">
                        <p><span>John Doe</span> sent a message to <span>Support</span></p>
                        <span class="badge">Sent</span>
                        <p class="t-time">08:10 AM</p>
                    </div>
                </div>

                <!-- App Open Event -->
                <div class="item-timeline timeline-warning">
                    <div class="t-dot" title="App Open">
                    </div>
                    <div class="t-text">
                        <p><span>Jane Smith</span> opened the app from <span>IP 103.243.198.29</span></p>
                        <span class="badge">Active</span>
                        <p class="t-time">08:12 AM</p>
                    </div>
                </div>

                <!-- App Close Event -->
                <div class="item-timeline timeline-dark">
                    <div class="t-dot" title="App Close">
                    </div>
                    <div class="t-text">
                        <p><span>John Doe</span> closed the app</p>
                        <span class="badge">Inactive</span>
                        <p class="t-time">08:15 AM</p>
                    </div>
                </div>

                <!-- New User Login -->
                <div class="item-timeline timeline-success">
                    <div class="t-dot" title="New User Login">
                    </div>
                    <div class="t-text">
                        <p>User <span>Ahmad Zulkifli</span> logged in from <span>IP 60.48.125.78</span></p>
                        <span class="badge">Success</span>
                        <p class="t-time">08:30 AM</p>
                    </div>
                </div>

                <!-- File Download -->
                <div class="item-timeline timeline-secondary">
                    <div class="t-dot" title="File Download">
                    </div>
                    <div class="t-text">
                        <p><span>Jane Smith</span> downloaded the file <span>"User_Guide.pdf"</span></p>
                        <span class="badge">Downloaded</span>
                        <p class="t-time">08:45 AM</p>
                    </div>
                </div>

                <!-- Chat Reply -->
                <div class="item-timeline timeline-primary">
                    <div class="t-dot" title="Chat Reply">
                    </div>
                    <div class="t-text">
                        <p><span>Support</span> replied to <span>John Doe</span>'s message</p>
                        <span class="badge">Replied</span>
                        <p class="t-time">09:00 AM</p>
                    </div>
                </div>

                <!-- User Logout -->
                <div class="item-timeline timeline-danger">
                    <div class="t-dot" title="User Logout">
                    </div>
                    <div class="t-text">
                        <p>User <span>Ahmad Zulkifli</span> logged out from <span>IP 60.48.125.78</span></p>
                        <span class="badge">Logout</span>
                        <p class="t-time">09:15 AM</p>
                    </div>
                </div>

                <!-- Notification Sent -->
                <div class="item-timeline timeline-warning">
                    <div class="t-dot" title="Notification Sent">
                    </div>
                    <div class="t-text">
                        <p>System sent a notification to <span>Jane Smith</span> regarding <span>"Account Activity"</span></p>
                        <span class="badge">Sent</span>
                        <p class="t-time">09:30 AM</p>
                    </div>
                </div>

                <!-- User Feedback Submitted -->
                <div class="item-timeline timeline-dark">
                    <div class="t-dot" title="Feedback Submitted">
                    </div>
                    <div class="t-text">
                        <p>User <span>John Doe</span> submitted feedback on <span>"App Performance"</span></p>
                        <span class="badge">Submitted</span>
                        <p class="t-time">09:45 AM</p>
                    </div>
                </div>

                <!-- Security Alert -->
                <div class="item-timeline timeline-danger">
                    <div class="t-dot" title="Security Alert">
                    </div>
                    <div class="t-text">
                        <p><span>Suspicious login attempt</span> detected from <span>IP 182.1.22.50</span></p>
                        <span class="badge">Alert</span>
                        <p class="t-time">10:00 AM</p>
                    </div>
                </div>

            </div>
        </div>

        <div class="tm-action-btn">
            <button class="btn"><span>View All</span> 
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right">
                    <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </button>
        </div>
    </div>
</div>
