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
        Schema::create('rent_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rent_id');
            $table->unsignedBigInteger('item_id');
            $table->integer("price");
            $table->integer("amount");
            $table->timestamps();

            $table->foreign('rent_id')->references("id")->on("rents")->onDelete('cascade');
            $table->foreign('item_id')->references("id")->on("items")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rent_items');
    }
};
