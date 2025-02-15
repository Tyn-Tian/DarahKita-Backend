<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDonationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->enum('status', ['pending', 'success', 'failed']);
            $table->foreignUuid('donor_id')->constrained('donors')->cascadeOnDelete();
            $table->foreignUuid('donor_schedule_id')->nullable()->constrained('donor_schedules')->cascadeOnDelete();
            $table->foreignUuid('pmi_center_id')->nullable()->constrained('pmi_centers')->cascadeOnDelete();
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
        Schema::dropIfExists('donations');
    }
}
