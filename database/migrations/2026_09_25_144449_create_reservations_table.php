<?php

use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')
                ->constrained('resources')
                ->onDelete('restrict');

            $table->unsignedInteger('units');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status')->default(ReservationStatus::PENDING->value);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'status', 'start_time', 'end_time'], 'idx_reservations_capacity_check');

            $table->index(['status', 'expires_at'], 'idx_reservations_expiry_check');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
