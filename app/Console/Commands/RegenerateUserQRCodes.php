<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class RegenerateUserQRCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:regenerate-qrcodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate QR codes for all users with a valid referral link';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = User::whereNotNull('referral_link')->where('referral_link', '!=', '')->get();

        foreach ($users as $user) {
            try {
                $qrDirectory = storage_path('app/public/qr');
                if (!is_dir($qrDirectory)) {
                    mkdir($qrDirectory, 0755, true);
                }

                $qrImagePath = 'qr/' . $user->id . '_qr.png';
                $qrFullPath = storage_path('app/public/' . $qrImagePath);

                \QrCode::format('png')
                    ->size(300)
                    ->generate($user->referral_link, $qrFullPath);

                $user->qr_link = 'storage/' . $qrImagePath;
                $user->save();

                $this->info("QR Code generated for user ID: {$user->id}");
            } catch (\Exception $e) {
                $this->error("Failed to generate QR code for user ID: {$user->id}. Error: " . $e->getMessage());
            }
        }

        $this->info('QR code regeneration process completed.');
        return 0;
    }
}
