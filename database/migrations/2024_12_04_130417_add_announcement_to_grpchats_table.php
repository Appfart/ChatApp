<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('grpchats', function (Blueprint $table) {
            $table->text('announcement')->nullable()->after('owner'); // Replace 'column_name' with the column you want it to follow.
        });
    }
    
    public function down()
    {
        Schema::table('grpchats', function (Blueprint $table) {
            $table->dropColumn('announcement');
        });
    }
};
