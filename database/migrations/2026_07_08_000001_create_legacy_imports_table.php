<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('recording_session_id')->constrained('recording_sessions')->cascadeOnDelete();
            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            $table->unique(['source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_imports');
    }
};