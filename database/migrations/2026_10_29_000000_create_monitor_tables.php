<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Último resultado de cada verificação (a página lê daqui).
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('status', 10);
            $table->string('summary', 500);
            $table->json('metrics')->nullable();
            $table->timestamp('since');
            $table->timestamp('checked_at');
        });

        // Números para os gráficos. Estreita de propósito (≈ 30 linhas por minuto): `at` é o minuto em tempo Unix; depois
        // de 48 h os minutos passam a médias de 15 (monitor:prune).
        Schema::create('monitor_samples', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 100);
            $table->double('value');
            $table->unsignedInteger('at');
            $table->index(['metric', 'at']);
            $table->index('at');
        });

        // Alertas: um incidente por série (abriu, agravou, recuperou).
        Schema::create('monitor_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('check');
            $table->string('label');
            $table->string('status', 10);
            $table->string('summary', 500);
            $table->timestamp('started_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_incidents');
        Schema::dropIfExists('monitor_samples');
        Schema::dropIfExists('monitor_checks');
    }
};
