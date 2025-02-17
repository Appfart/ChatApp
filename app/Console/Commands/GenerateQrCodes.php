<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use QrCode;

class GenerateQrCodes extends Command
{
    protected $signature = 'generate:qr-codes';
    protected $description = 'Generate QR codes for users with referral links';

    public function handle()
    {
        // Ensure the directory exists
        $qrDirectory = storage_path('app/public/qr');
        if (!is_dir($qrDirectory)) {
            mkdir($qrDirectory, 0755, true);
        }

        $users = User::whereNotNull('referral_link')->get();

        foreach ($users as $user) {
            $qrImagePath = 'qr/' . $user->id . '_qr.png'; // Relative path for public storage
            $qrFullPath = storage_path('app/' . $qrImagePath); // Absolute path

            \QrCode::format('png')
                ->size(300)
                ->generate($user->referral_link, $qrFullPath);

            $user->qr_link = 'storage/' . $qrImagePath; // Publicly accessible URL
            $user->save();

            $this->info("QR code generated for user ID: {$user->id}");
        }
    }
}