<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDonorSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('donor_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('location');
            $table->time('time');
            $table->foreignUuid('pmi_center_id')->constrained('pmi_centers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('donor_schedules');
    }
}
