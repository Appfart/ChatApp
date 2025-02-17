<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAvatarToGrpchatsTable extends Migration
{
    public function up()
    {
        Schema::table('grpchats', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('chatname');
        });
    }

    public function down()
    {
        Schema::table('grpchats', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
}
