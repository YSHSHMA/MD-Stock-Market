<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('daily_metrics', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->foreignUuid('ticker_id')->constrained()->onDelete('cascade');
      $table->date('date');
      $table->decimal('price', 18, 8);
      $table->decimal('change', 18, 8)->nullable();
      $table->decimal('change_percent', 18, 8)->nullable();
      $table->timestamps();

      $table->unique(['ticker_id', 'date']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('daily_metrics');
  }
};
