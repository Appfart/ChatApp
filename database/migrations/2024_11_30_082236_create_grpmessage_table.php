<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrpmessageTable extends Migration
{
    public function up()
    {
        Schema::create('grpmessage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grpchat_id'); // Foreign key to grpchats table
            $table->unsignedBigInteger('user_id'); // Foreign key to users table
            $table->text('message')->nullable()->collation('utf8mb4_unicode_ci'); // Allow Chinese and emoji
            $table->string('image_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('doc_url')->nullable();
            $table->boolean('status')->default(1); // Default active
            $table->timestamps();

            // Add foreign key constraints
            $table->foreign('grpchat_id')->references('id')->on('grpchats')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('grpmessage');
    }
}
