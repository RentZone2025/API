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
        Schema::create('rents', function (Blueprint $table) {
            $table->id();
            $table->text("name");
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('device_id');
            $table->integer('rent_period');
            $table->date('delevery_date')->default(null);
            $table->date('back_date')->default(null);
            $table->string("status")->default("feldolgozás");
            $table->integer('sum_price');
            $table->integer('sale')->default(0); 
            $table->boolean('is_free_shipping')->default(false);
            $table->boolean('is_free_deposit')->default(false); 
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references("id")->on("users")->onDelete('cascade');
            $table->foreign('device_id')->references("id")->on("devices")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rents');
    }
};
