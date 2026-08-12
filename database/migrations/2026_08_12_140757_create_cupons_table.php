<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cupom resgatável por código, aplicado sobre o subtotal do carrinho.
     */
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->id();

            // O Model normaliza para maiúsculo antes de gravar. A unicidade não pode
            // depender da collation: MySQL 8 é case-insensitive e SQLite não é, o que
            // faria este teste passar num banco e falhar no outro.
            $table->string('codigo', 32)->unique();

            $table->enum('tipo', ['percentual', 'fixo']);
            $table->decimal('valor', 10, 2);
            $table->decimal('valor_minimo', 10, 2)->default(0);

            // null = ilimitado
            $table->unsignedInteger('limite_uso')->nullable();
            $table->unsignedInteger('usos')->default(0);

            $table->foreignId('campanha_id')->nullable()->constrained('campanhas')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupons');
    }
};
