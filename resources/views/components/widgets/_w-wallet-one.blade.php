<div class="widget widget-wallet-one">
    
    <div class="wallet-info text-center mb-3">
        <p class="wallet-title mb-3">{{$title}}</p>
        <p class="total-amount mb-3">Total Balance: $26,177.88</p>
    </div>

    <hr>

    <ul class="list-group list-group-media">
        <li class="list-group-item ">
            <div class="media">
                <div class="me-3">
                    <img alt="avatar" src="{{ asset('resources/images/freeze-wallet.svg') }}" class="img-fluid rounded-circle">
                </div>
                <div class="media-body">
                    <h6 class="tx-inverse">Freeze Wallet</h6>
                    <p class="amount">- $5,000.00</p>
                </div>
            </div>
        </li>
        <li class="list-group-item">
            <div class="media">
                <div class="me-3">
                    <img alt="avatar" src="{{ asset('resources/images/deposit-wallet.svg') }}" class="img-fluid rounded-circle">
                </div>
                <div class="media-body">
                    <h6 class="tx-inverse">Deposit Wallet</h6>
                    <p class="amount">- $10,000.00</p>
                </div>
            </div>
        </li>
        <li class="list-group-item">
            <div class="media">
                <div class="me-3">
                    <img alt="avatar" src="{{ asset('resources/images/credit-balance.svg') }}" class="img-fluid rounded-circle">
                </div>
                <div class="media-body">
                    <h6 class="tx-inverse">Credit Balance</h6>
                    <p class="amount">$11,177.88</p>
                </div>
            </div>
        </li>
    </ul>

    <button class="btn btn-secondary w-100 mt-3">View Transaction History</button>
    
</div>
