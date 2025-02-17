<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrpchatsTable extends Migration
{
    public function up()
    {
        Schema::create('grpchats', function (Blueprint $table) {
            $table->id();
            $table->string('chatname')->collation('utf8mb4_unicode_ci'); // For Chinese and emoji support
            $table->json('members');
            $table->json('quitmembers');
            $table->json('admins');
            $table->unsignedBigInteger('owner');
            $table->boolean('status')->default(1);
            $table->timestamps();

            // Add foreign key constraint for the owner
            $table->foreign('owner')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('grpchats');
    }
}
