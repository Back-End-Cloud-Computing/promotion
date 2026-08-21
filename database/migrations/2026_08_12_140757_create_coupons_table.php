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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // O Model normaliza para maiúsculo antes de gravar. A unicidade não pode
            // depender da collation: MySQL 8 é case-insensitive e SQLite não é, o que
            // faria este teste passar num banco e falhar no outro.
            $table->string('code', 32)->unique();

            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->decimal('minimum_value', 10, 2)->default(0);

            // null = ilimitado
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
