<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // Show the dashboard page
    public function index()
    {
        // Pass the title or any other data you want to send to the view
        return view('pages.app.dashboard', ['title' => 'Dashboard']);
    }
}
