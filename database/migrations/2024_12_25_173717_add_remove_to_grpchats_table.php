<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRemoveToGrpchatsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('grpchats', function (Blueprint $table) {
            $table->json('remove')->nullable()->after('read_timestamps')->comment('User removal timestamps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grpchats', function (Blueprint $table) {
            $table->dropColumn('remove');
        });
    }
}