<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angel_one_connections', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('client_code', 64);
            $table->text('encrypted_secrets');
            $table->timestamps();
        });

        Schema::create('angel_one_market_subscriptions', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('underlying', 16);
            $table->date('expiry');
            $table->timestamp('last_seen_at')->index();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('angel_one_market_subscriptions');
        Schema::dropIfExists('angel_one_connections');
    }
};
