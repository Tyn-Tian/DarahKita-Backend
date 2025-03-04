<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePhysicalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('physicals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->float('systolic')->nullable();
            $table->float('diastolic')->nullable();
            $table->float('pulse')->nullable();
            $table->float('weight')->nullable();
            $table->float('temperatur')->nullable();
            $table->float('hemoglobin')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('physicals');
    }
}
