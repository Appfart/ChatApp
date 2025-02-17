<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMessageUserTagsTable extends Migration
{
    public function up()
    {
        Schema::create('message_user_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            // Foreign Keys
            $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Ensure unique tag per user per message
            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_user_tags');
    }
}
