<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Supports any UTF-8 characters
            $table->unsignedBigInteger('user_id'); // User ID column without a foreign key constraint
            $table->json('member_id')->nullable(); // JSON field for array of user IDs
            $table->boolean('status')->default(1); // Default status 1
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
}
