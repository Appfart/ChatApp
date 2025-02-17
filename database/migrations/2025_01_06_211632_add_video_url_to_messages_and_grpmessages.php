<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideoUrlToMessagesAndGrpmessages extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('doc_url');
        });

        Schema::table('grpmessage', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('doc_url');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });

        Schema::table('grpmessage', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
}
