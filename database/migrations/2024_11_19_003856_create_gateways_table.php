<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGatewaysTable extends Migration
{
    public function up()
    {
        Schema::create('gateways', function (Blueprint $table) {
            $table->id();
            $table->string('bankname');
            $table->string('accname')->nullable();
            $table->string('accno')->nullable();
            $table->string('iban')->nullable();
            $table->string('branch')->nullable();
            $table->boolean('status')->default(1); // 1 = Active, 0 = Inactive
            $table->string('method')->default('local'); // Default to "local"
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gateways');
    }
}
