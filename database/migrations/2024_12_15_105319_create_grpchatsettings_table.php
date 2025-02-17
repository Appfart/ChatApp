<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGrpchatsettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grpchatsettings', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('grpchat_id'); // Link to grpchats table
            $table->boolean('add_friend')->default(0); // On/Off setting
            $table->boolean('hide_members')->default(0); // On/Off setting
            $table->boolean('hide_allmembers')->default(0); // On/Off setting
            $table->boolean('allow_invite')->default(0); // On/Off setting
            $table->boolean('allow_qrinvite')->default(0); // On/Off setting
            $table->boolean('kyc')->default(0); // On/Off setting
            $table->boolean('block_quit')->default(0); // On/Off setting
            $table->boolean('mute_chat')->default(0); // On/Off setting
            $table->json('mute_members')->nullable(); // JSON column for member IDs
            $table->timestamps(); // created_at and updated_at

            // Foreign key constraint
            $table->foreign('grpchat_id')->references('id')->on('grpchats')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grpchatsettings');
    }
}
