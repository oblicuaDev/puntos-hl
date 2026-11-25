<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos OTP de 4 dígitos enviados por SMS para ingresar al portal (HL-25)
 * o verificar el celular al registrarse. El código nunca se guarda en texto
 * plano: solo su HMAC-SHA256 con la APP_KEY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('purpose', 20); // login | register
            $table->string('phone', 20);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_login_codes');
    }
};
