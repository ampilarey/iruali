<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // iruali's own costs (Admin → Finance), for the monthly profit and loss
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('spent_on')->index();
            $table->string('category', 30);
            $table->string('description', 200);
            $table->string('supplier', 120)->nullable();
            $table->decimal('amount', 12, 2); // what was paid, GST included
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->string('receipt_path')->nullable();
            $table->boolean('recurring')->default(false); // copied every month (finance:recurring-expenses)
            $table->foreignId('repeat_of')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
