<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReplyToIdToGrpmessagesTable extends Migration
{
    public function up()
    {
        Schema::table('grpmessage', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->foreign('reply_to_id')->references('id')->on('grpmessages')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('grpmessage', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn('reply_to_id');
        });
    }
}
