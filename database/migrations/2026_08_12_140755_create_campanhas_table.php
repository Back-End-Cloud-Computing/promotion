<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campanha é o guarda-chuva de vigência. Promoções e cupons apontam para ela
     * em vez de repetirem inicia_em/termina_em, o que deixaria três lugares para
     * errar a mesma comparação de data.
     */
    public function up(): void
    {
        Schema::create('campanhas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->dateTime('inicia_em');
            $table->dateTime('termina_em');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['ativo', 'inicia_em', 'termina_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanhas');
    }
};
