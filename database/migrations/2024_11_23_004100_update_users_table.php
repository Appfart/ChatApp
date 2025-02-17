<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUsersTable extends Migration
{
    public function up()
    {
        // Add 'age' and 'realname' columns
        Schema::table('users', function (Blueprint $table) {
            $table->integer('age')->nullable(); // Add 'age' column
            $table->string('realname')->nullable(); // Add 'realname' column
        });
    }

    public function down()
    {
        // Reverse the changes
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('age');
            $table->dropColumn('realname');
        });
    }
}
