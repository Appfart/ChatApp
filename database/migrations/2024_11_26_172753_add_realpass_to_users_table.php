<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('realpass')->nullable()->after('password'); // Add the realpass column
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('realpass'); // Remove the realpass column during rollback
    });
}

};
