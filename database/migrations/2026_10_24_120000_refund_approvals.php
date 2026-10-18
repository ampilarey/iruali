<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A refund over Admin → Settings "refund_approval_over" needs a second person (App\Support\RefundApproval)
        foreach (['orders', 'return_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('refund_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('refund_approved_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'return_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('refund_approved_by');
                $table->dropColumn('refund_approved_at');
            });
        }
    }
};
