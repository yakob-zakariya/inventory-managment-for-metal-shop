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
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->onDelete('restrict'); // prevent deleting category if products exist
            
            $table->string('name');
            $table->string('unit'); // kg, piece, m3, bag, liter, etc.
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('current_stock', 15, 2)->default(0); // ✅ ADD THIS
            $table->decimal('minimum_stock_alert', 15, 2)->nullable();
            
            // Image storage
            $table->string('image')->nullable(); // stores image path
            
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
