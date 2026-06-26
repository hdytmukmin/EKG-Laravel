<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ekg_raw_signals', function (Blueprint $table) {
            if (! Schema::hasColumn('ekg_raw_signals', 'filtered_values')) {
                $table->json('filtered_values')->nullable()->after('voltage_values');
            }

            if (! Schema::hasColumn('ekg_raw_signals', 'r_peak_indices')) {
                $table->json('r_peak_indices')->nullable()->after('filtered_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ekg_raw_signals', function (Blueprint $table) {
            if (Schema::hasColumn('ekg_raw_signals', 'r_peak_indices')) {
                $table->dropColumn('r_peak_indices');
            }

            if (Schema::hasColumn('ekg_raw_signals', 'filtered_values')) {
                $table->dropColumn('filtered_values');
            }
        });
    }
};
