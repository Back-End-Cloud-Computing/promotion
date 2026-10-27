<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedidos já processados a partir do evento OrderConfirmed. O `order_id`
     * como chave primária é a idempotência: a reentrega do mesmo pedido bate na
     * unicidade e não consome o cupom de novo.
     */
    public function up(): void
    {
        Schema::create('processed_orders', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->string('coupon_code', 32)->nullable();
            $table->string('outcome', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_orders');
    }
};
