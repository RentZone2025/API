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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->text("name");
            $table->unsignedBigInteger('type_id');
            $table->integer('count');
            $table->longtext("description");
            $table->integer('unit');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('type_id')->references("id")->on("device_types")->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
