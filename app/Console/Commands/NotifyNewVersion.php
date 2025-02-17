<?php
// app/Console/Commands/NotifyNewVersion.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Pusher\Pusher;

class NotifyNewVersion extends Command
{
    protected $signature = 'notify:version {version} {downloadUrl}';
    protected $description = 'Notify users about a new app version';

    public function handle()
    {
        $version = $this->argument('version');
        $downloadUrl = $this->argument('downloadUrl');

        // Initialize Pusher
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true
            ]
        );

        // Send Pusher event
        $pusher->trigger('version-channel', 'version-update', [
            'version' => $version,
            'download_url' => $downloadUrl,
        ]);

        $this->info("Version update notification sent: $version");
    }
}
