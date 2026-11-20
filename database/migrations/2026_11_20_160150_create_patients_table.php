<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('document_type')->default('CC');
            $table->string('document_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('password');
            $table->boolean('must_change_password')->default(true);
            $table->enum('registration_source', ['manual_admin', 'self_registered'])->default('manual_admin');
            $table->foreignId('created_by_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
