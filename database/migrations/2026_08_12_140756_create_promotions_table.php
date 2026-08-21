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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            // Sem foreign key: o catálogo vive no MongoDB do serviço de Produto.
            // Uma FK atravessando serviço e banco é impossível aqui, não esquecimento.
            $table->unsignedBigInteger('product_id')->unique();

            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->unsignedTinyInteger('discount_percentage');

            // Valores em português: espelham a categorização real do catálogo do
            // serviço de Produto (outro microsserviço), não um nome nosso.
            $table->enum('category', ['Superiores', 'Inferiores', 'Inverno']);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
