<x-base-layout :scrollspy="false">
    <x-slot:pageTitle>
        Create New Ticket
    </x-slot>

    <!-- BEGIN GLOBAL MANDATORY STYLES -->
    <x-slot:headerFiles>
        @vite(['resources/scss/light/assets/components/modal.scss'])
        @vite(['resources/scss/light/assets/apps/contacts.scss'])

        @vite(['resources/scss/dark/assets/components/modal.scss'])
        @vite(['resources/scss/dark/assets/apps/contacts.scss'])
    </x-slot>
    <!-- END GLOBAL MANDATORY STYLES -->

    <div class="container mt-4">
        <h1>Create New Ticket</h1>
        <form action="{{ route('tickets.store') }}" method="POST">
            @csrf
            <div class="form-group mb-3">
                <label for="subject">Subject</label>
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="form-group mb-3">
                <label for="message">Message</label>
                <textarea name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Ticket</button>
        </form>
    </div>

    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <x-slot:footerFiles>
        @vite(['resources/assets/js/custom.js'])
        <script src="{{asset('plugins/global/vendors.min.js')}}"></script>
        <script src="{{asset('plugins/jquery-ui/jquery-ui.min.js')}}"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
        <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
        <script src="{{ asset('js/ticket.js') }}"></script>
        @vite(['resources/assets/js/apps/contact.js'])
    </x-slot>
    <!-- END PAGE LEVEL SCRIPTS -->
</x-base-layout>
