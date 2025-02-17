<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lucks', function (Blueprint $table) {
            $table->id();
            $table->string('txid')->unique(); // Transaction ID
            $table->unsignedBigInteger('owner'); // Owner (user ID)
            $table->decimal('amount', 15, 2);
            $table->string('status', 20); // e.g., active, expired
            $table->boolean('claimed')->default(false);
            $table->timestamps();
    
            $table->foreign('owner')->references('id')->on('users')->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lucks');
    }
};
