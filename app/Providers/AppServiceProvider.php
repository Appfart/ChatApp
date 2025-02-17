<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View; // Import View facade
use Illuminate\Support\Facades\Auth; // Import Auth facade
use App\Models\Ticket; // Import Ticket model

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        Blade::if('role', function ($role) {
            return Auth::check() && Auth::user()->role === $role;
        });

        // **Add the following View Composer**
        View::composer('components.menu.vertical-menu', function ($view) {
            // Fetch the count of pending tickets
            $pendingTicketCount = Ticket::where('status', 'pending')->count();
            
            // Pass the count to the view
            $view->with('pendingTicketCount', $pendingTicketCount);
        });
    }
}
