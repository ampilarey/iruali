<?php

use App\Support\IslandList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Every inhabited island (database/data/islands.json, 207 islands) with its atoll in Dhivehi, map
 * position (for "Use my location") and a stable code. Production only had the four starter
 * islands; they are matched by name so addresses keep pointing at the same rows, and "Addu City"
 * (not one island) is switched off unless something points at it. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('islands', function (Blueprint $table) {
            if (! Schema::hasColumn('islands', 'code')) {
                $table->string('code', 80)->nullable()->unique()->after('id');
                $table->unsignedSmallInteger('salat_id')->nullable()->after('code');
                $table->string('atoll_dv', 50)->nullable()->after('atoll');
                $table->decimal('latitude', 9, 6)->nullable()->after('atoll_dv');
                $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
                $table->index(['is_active', 'atoll']);
            }
        });

        $result = IslandList::load();
        if ($result['kept'] || $result['deactivated']) {
            Log::info('Island list loaded', $result);
        }
    }

    public function down(): void
    {
        // The loaded islands stay (addresses may point at them); only the new columns go
        Schema::table('islands', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'atoll']);
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'salat_id', 'atoll_dv', 'latitude', 'longitude']);
        });
    }
};
