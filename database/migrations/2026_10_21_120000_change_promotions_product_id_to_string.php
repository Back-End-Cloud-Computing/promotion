<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// O Product identifica produtos por ObjectId do Mongo (string de 24 caracteres),
// então o product_id não pode mais ser inteiro. A constraint UNIQUE continua.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('product_id', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->change();
        });
    }
};
