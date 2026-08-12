<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desconto automático por produto, espelhando a tabela `sale` do projeto de
     * referência da equipe.
     */
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table) {
            $table->id();

            // Sem foreign key: o catálogo vive no MongoDB do serviço de Produto.
            // Uma FK atravessando serviço e banco é impossível aqui, não esquecimento.
            $table->unsignedBigInteger('produto_id')->unique();

            $table->foreignId('campanha_id')->nullable()->constrained('campanhas')->nullOnDelete();
            $table->unsignedTinyInteger('desconto_pct');
            $table->enum('categoria', ['Superiores', 'Inferiores', 'Inverno']);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['ativo', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocoes');
    }
};
