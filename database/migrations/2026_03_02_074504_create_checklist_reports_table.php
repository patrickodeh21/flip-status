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
        Schema::create('checklist_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('cleaning_sessions')->onDelete('cascade');
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->string('type'); // damage, maintenance, supplies, safety, other
            $table->string('location'); // room id or 'general'
            $table->text('description');
            $table->string('priority'); // low, medium, high
            $table->string('status')->default('pending'); // pending, in_progress, resolved
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_reports');
    }
};
