<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price'); // in rupiah
            $table->unsignedInteger('inventory_count')->default(0);
 
            // Flash sale fields
            $table->boolean('is_flash_sale')->default(false);
            $table->unsignedBigInteger('flash_sale_price')->nullable(); // discounted price in rupiah
            $table->timestamp('flash_sale_starts_at')->nullable();
            $table->timestamp('flash_sale_ends_at')->nullable();
 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
