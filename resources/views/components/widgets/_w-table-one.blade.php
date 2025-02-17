{{-- 
/**
*
* Created a new component <x-rtl.widgets._w-table-one/>.
* 
*/
--}}

<div class="widget widget-table-one">
    <div class="widget-heading">
        <h5 class="">{{$title}}</h5>
        <div class="task-action">
            <div class="dropdown">
                <a class="dropdown-toggle" href="#" role="button" id="transactions" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-horizontal"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" y="12" r="1"></circle></svg>
                </a>

                <div class="dropdown-menu left" aria-labelledby="transactions">
                    <a class="dropdown-item" href="javascript:void(0);">View Report</a>
                    <a class="dropdown-item" href="javascript:void(0);">Edit Report</a>
                    <a class="dropdown-item" href="javascript:void(0);">Mark as Done</a>
                </div>
            </div>
        </div>
    </div>

    <div class="widget-content">
        <!-- Deposit from Client -->
        <div class="transactions-list">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">DC</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Deposit from Client</h4>
                        <p class="meta-date">05 Mar 2:30PM</p>
                    </div>
                </div>
                <div class="t-rate rate-inc">
                    <p><span>+$250.00</span></p>
                </div>
            </div>
        </div>

        <!-- Credit by Support -->
        <div class="transactions-list t-info">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">CS</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Credit by Support</h4>
                        <p class="meta-date">06 Mar 11:00AM</p>
                    </div>
                </div>
                <div class="t-rate rate-inc">
                    <p><span>+$50.00</span></p>
                </div>
            </div>
        </div>

        <!-- Account Freeze -->
        <div class="transactions-list">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">AF</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Account Freeze</h4>
                        <p class="meta-date">07 Mar 3:00PM</p>
                    </div>
                </div>
                <div class="t-rate rate-dec">
                    <p><span>-$100.00</span></p>
                </div>
            </div>
        </div>

        <!-- Transfer by User -->
        <div class="transactions-list t-secondary">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">TU</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Transfer by User</h4>
                        <p class="meta-date">07 Mar 4:30PM</p>
                    </div>
                </div>
                <div class="t-rate rate-dec">
                    <p><span>-$75.00</span></p>
                </div>
            </div>
        </div>

        <!-- Debit by Support -->
        <div class="transactions-list t-info">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">DS</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Debit by Support</h4>
                        <p class="meta-date">08 Mar 10:00AM</p>
                    </div>
                </div>
                <div class="t-rate rate-dec">
                    <p><span>-$30.00</span></p>
                </div>
            </div>
        </div>

        <!-- User Withdrawal -->
        <div class="transactions-list">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">UW</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>User Withdrawal</h4>
                        <p class="meta-date">08 Mar 2:00PM</p>
                    </div>
                </div>
                <div class="t-rate rate-dec">
                    <p><span>-$200.00</span></p>
                </div>
            </div>
        </div>

        <!-- Client Refund -->
        <div class="transactions-list t-secondary">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">CR</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Client Refund</h4>
                        <p class="meta-date">09 Mar 5:00PM</p>
                    </div>
                </div>
                <div class="t-rate rate-dec">
                    <p><span>-$150.00</span></p>
                </div>
            </div>
        </div>

        <!-- Deposit from Bank -->
        <div class="transactions-list">
            <div class="t-item">
                <div class="t-company-name">
                    <div class="t-icon">
                        <div class="avatar">
                            <span class="avatar-title">DB</span>
                        </div>
                    </div>
                    <div class="t-name">
                        <h4>Deposit from Bank</h4>
                        <p class="meta-date">10 Mar 11:00AM</p>
                    </div>
                </div>
                <div class="t-rate rate-inc">
                    <p><span>+$500.00</span></p>
                </div>
            </div>
        </div>
    </div>
</div>
