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
        Schema::create('personal_refresh_tokens', function (Blueprint $table) {
            $table->id();
            // uuidMorphs, no morphs: los modelos autenticables de la red
            // Clousis usan uuid como clave primaria. Si tu app usa ids
            // autoincrementales, cambiá esto a morphs() antes de migrar.
            $table->uuidMorphs('tokenable');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['tokenable_id', 'tokenable_type', 'token'], 'personal_tokens_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_refresh_tokens');
    }
};