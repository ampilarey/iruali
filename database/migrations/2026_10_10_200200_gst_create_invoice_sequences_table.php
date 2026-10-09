<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The last invoice number used in each series: one series per shop ("shop:12", "shop:0" for
     * iruali's own sales) and one for iruali's commission invoices ("commission"). The row is
     * locked while a number is taken, so two payments at the same moment never get the same one.
     */
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('series', 40)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        DB::table('invoice_sequences')->insert(['series' => 'commission', 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
