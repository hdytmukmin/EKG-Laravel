<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puskesmas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('puskesmas_id')->references('id')->on('puskesmas')->nullOnDelete();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained('puskesmas')->cascadeOnDelete();
            $table->string('name');
            $table->string('device_uid')->unique();
            $table->string('mqtt_client_id')->nullable();
            $table->string('status')->default('unknown');
            $table->timestamp('last_seen_at')->nullable();
            $table->json('topic_map')->nullable();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained('puskesmas')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('external_subject_id')->nullable();
            $table->timestamps();
            $table->index(['puskesmas_id', 'name']);
        });

        Schema::create('recording_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puskesmas_id')->constrained('puskesmas')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('source')->default('mqtt');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['puskesmas_id', 'recorded_at']);
        });

        Schema::create('ekg_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_session_id')->unique()->constrained('recording_sessions')->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->decimal('interval_pt', 12, 6)->nullable();
            $table->decimal('bpm', 12, 6)->nullable();
            $table->decimal('rr', 12, 6)->nullable();
            $table->decimal('rr_lokal', 12, 6)->nullable();
            $table->string('status')->nullable();
            $table->decimal('sdnn', 12, 6)->nullable();
            $table->decimal('sns', 12, 6)->nullable();
            $table->json('heart_rate')->nullable();
            $table->timestamps();
        });

        Schema::create('ekg_raw_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_session_id')->unique()->constrained('recording_sessions')->cascadeOnDelete();
            $table->json('voltage_values');
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedInteger('total_samples')->default(0);
            $table->timestamps();
        });

        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_session_id')->unique()->constrained('recording_sessions')->cascadeOnDelete();
            $table->string('label')->default('PENDING_MODEL');
            $table->decimal('confidence', 6, 4)->nullable();
            $table->string('model_version')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('predicted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['puskesmas_id']);
        });

        Schema::dropIfExists('predictions');
        Schema::dropIfExists('ekg_raw_signals');
        Schema::dropIfExists('ekg_features');
        Schema::dropIfExists('recording_sessions');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('puskesmas');
    }
};
