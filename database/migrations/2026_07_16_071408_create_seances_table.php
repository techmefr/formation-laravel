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
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('coach_id')->constrained('users');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('max_participants')->nullable();
            $table->string('recurrence')->default('none');
            $table->date('recurrence_until')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
