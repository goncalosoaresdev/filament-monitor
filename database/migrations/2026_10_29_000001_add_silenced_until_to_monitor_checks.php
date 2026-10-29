<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alertas silenciados até esta hora (página Monitorização, monitor:silence). O monitor:work nunca a escreve.
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->timestamp('silenced_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->dropColumn('silenced_until');
        });
    }
};
