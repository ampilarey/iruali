<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('recipient_name', 120);
            $table->string('phone', 20)->nullable();
            $table->foreignId('island_id')->nullable()->constrained('islands')->nullOnDelete();
            $table->string('atoll', 100)->nullable();
            $table->string('island', 100);
            $table->string('house_name_or_street', 255);
            $table->string('ward', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Maldives');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });

        // Customers who already typed an address on their profile get it as their default saved address.
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('address')->where('address', '!=', '')
            ->whereNotNull('city')->where('city', '!=', '')
            ->get(['id', 'name', 'phone', 'address', 'city', 'state', 'postal_code']);

        $islands = DB::table('islands')->get(['id', 'name', 'atoll'])->mapWithKeys(function ($row) {
            $name = json_decode($row->name, true);

            return [mb_strtolower(trim((string) ($name['en'] ?? ''))) => $row];
        });

        foreach ($users as $user) {
            $island = $islands[mb_strtolower(trim($user->city))] ?? null;
            DB::table('addresses')->insert([
                'user_id' => $user->id,
                'label' => 'Home',
                'recipient_name' => mb_substr($user->name, 0, 120),
                'phone' => $user->phone ? mb_substr($user->phone, 0, 20) : null,
                'island_id' => $island?->id,
                'atoll' => $user->state ? mb_substr($user->state, 0, 100) : ($island?->atoll),
                'island' => mb_substr($user->city, 0, 100),
                'house_name_or_street' => mb_substr($user->address, 0, 255),
                'postal_code' => $user->postal_code ? mb_substr($user->postal_code, 0, 20) : null,
                'country' => 'Maldives',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
