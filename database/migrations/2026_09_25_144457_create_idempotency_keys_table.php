<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('endpoint');
            $table->string('request_hash');
            $table->integer('response_code');
            $table->json('response_body');
            $table->timestamps();

            $table->unique(['key', 'endpoint'], 'uniq_idempotency_key_endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
