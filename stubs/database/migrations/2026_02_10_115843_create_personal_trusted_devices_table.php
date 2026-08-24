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
        Schema::create('personal_trusted_devices', function (Blueprint $table) {
            $table->id();
            // uuidMorphs, no morphs: los modelos autenticables de la red
            // Clousis usan uuid como clave primaria. Si tu app usa ids
            // autoincrementales, cambiá esto a morphs() antes de migrar.
            $table->uuidMorphs('verifyable');
            $table->string('fingerprint', 64);
            $table->json('device_info')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('trusted_at')->nullable();
            $table->timestamps();
            $table->unique(['verifyable_id', 'verifyable_type', 'fingerprint'], 'model_device_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_trusted_devices');
    }
};