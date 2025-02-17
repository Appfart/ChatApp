<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReadTimestampsToConversationsAndGrpchatsTables extends Migration
{
    public function up()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->json('read_timestamps')->nullable()->after('target');
        });

        Schema::table('grpchats', function (Blueprint $table) {
            $table->json('read_timestamps')->nullable()->after('announcement');
        });
    }

    public function down()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('read_timestamps');
        });

        Schema::table('grpchats', function (Blueprint $table) {
            $table->dropColumn('read_timestamps');
        });
    }
}