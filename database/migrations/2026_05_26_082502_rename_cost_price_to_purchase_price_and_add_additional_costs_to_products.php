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
        Schema::table('products', function (Blueprint $table) {
            // Rename cost_price to purchase_price
            $table->renameColumn('cost_price', 'purchase_price');

            // Add additional_costs column (nullable)
            $table->decimal('additional_costs', 10, 2)->nullable()->after('purchase_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove additional_costs column
            $table->dropColumn('additional_costs');

            // Rename back to cost_price
            $table->renameColumn('purchase_price', 'cost_price');
        });
    }
};
