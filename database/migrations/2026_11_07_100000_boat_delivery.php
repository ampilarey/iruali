<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Send with a boat (App\Support\BoatDelivery): the shop takes the parcel to a jetty in Greater Malé and hands
 * it to the boat the customer named. The boat's name, phone, jetty, departure time, the island it goes to and
 * a note, as the customer gave them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('boat')->nullable()->after('delivery_zone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('boat');
        });
    }
};
