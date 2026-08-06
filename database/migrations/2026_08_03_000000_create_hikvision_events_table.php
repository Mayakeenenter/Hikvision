<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hikvision_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable()->index();
            $table->string('employee_id')->nullable()->index();
            $table->string('employee_name')->nullable()->index();
            $table->string('card_number')->nullable();
            $table->string('card_reader_id')->nullable();
            $table->string('event_type')->default('Access Control Event');
            $table->string('sub_type')->nullable();
            $table->string('major_type')->nullable();
            $table->string('status_badge')->nullable(); // checkIn, checkOut, authenticated, doorOpen, exitButton
            $table->timestamp('recorded_at')->nullable()->index();
            $table->date('event_date')->nullable()->index();
            $table->time('event_time')->nullable();
            $table->string('remote_host')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hikvision_events');
    }
};
