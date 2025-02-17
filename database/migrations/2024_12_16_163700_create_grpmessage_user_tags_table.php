<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrpmessageUserTagsTable extends Migration
{
    public function up()
    {
        Schema::create('grpmessage_user_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grpmessage_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('grpmessage_id')->references('id')->on('grpmessages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Ensure unique tag per user per group message
            $table->unique(['grpmessage_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('grpmessage_user_tags');
    }
}
