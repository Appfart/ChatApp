<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleAndReferralToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'admin', 'support', 'staff', 'client'])->default('client');
            $table->unsignedBigInteger('referral')->nullable()->comment('Upline user ID');
            $table->string('referral_link')->nullable()->comment('Auto-generated referral link');
            
            // Optional: Add a foreign key constraint on referral if user ID references are required
            $table->foreign('referral')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->dropForeign(['referral']);
            $table->dropColumn('referral');
            $table->dropColumn('referral_link');
        });
    }
}
