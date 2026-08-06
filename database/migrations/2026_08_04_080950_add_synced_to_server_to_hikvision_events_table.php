<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hikvision_events', function (Blueprint $table) {
            $table->boolean('synced_to_server')
                ->default(false)
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('hikvision_events', function (Blueprint $table) {
            $table->dropColumn('synced_to_server');
        });
    }
};