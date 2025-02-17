<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Admin', // Admin's name
            'email' => 'admin@example.com', // Admin's email
            'password' => Hash::make('password'), // Admin's password (change it to something secure)
            'email_verified_at' => now(),
        ]);
    }
}
