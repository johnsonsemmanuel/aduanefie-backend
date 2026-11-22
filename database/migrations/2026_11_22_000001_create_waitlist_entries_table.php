<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('waitlist_entries')) {
            return;
        }

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 40)->nullable();
            $table->string('interest', 60)->nullable();
            $table->string('source', 60)->default('homepage_popup');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
