<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('transactions', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
      $table->foreignUuid('ticker_id')->constrained()->onDelete('cascade');
      $table->enum('type', ['buy', 'sell']);
      $table->decimal('quantity', 18, 8);
      $table->decimal('price', 18, 8);
      $table->timestamp('transaction_date');
      $table->timestamps();

      $table->index(['user_id', 'ticker_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('transactions');
  }
};
