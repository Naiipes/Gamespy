<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->boolean('notifications_enabled')->default(false)->after('target_price');
            $table->boolean('notify_by_email')->default(false)->after('notifications_enabled');
            $table->boolean('use_target_price')->default(true)->after('notify_by_email');
        });

        DB::table('wishlists')
            ->where('target_price', '>', 0)
            ->update([
                'notifications_enabled' => true,
                'notify_by_email' => true,
                'use_target_price' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn(['notifications_enabled', 'notify_by_email', 'use_target_price']);
        });
    }
};
