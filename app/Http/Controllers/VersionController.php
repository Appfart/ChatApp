<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function getLatestVersion()
    {
        // You can store these in a config file or database
        $latestVersion = '2.0.1';
        $downloadUrl = 'https://qmxk.cloud/qmxk.apk'; // Fixed URL

        return response()->json([
            'version' => $latestVersion,
            'download_url' => $downloadUrl,
        ]);
    }
}
