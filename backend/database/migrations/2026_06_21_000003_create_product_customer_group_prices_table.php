<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_customer_group_prices', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('customer_group_id');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_customer_group_prices');
    }
};
