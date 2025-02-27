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
            $table->integer('systolic')->nullable();
            $table->integer('diastolic')->nullable();
            $table->integer('pulse')->nullable();
            $table->integer('weight')->nullable();
            $table->integer('temperatur')->nullable();
            $table->integer('hemoglobin')->nullable();
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
