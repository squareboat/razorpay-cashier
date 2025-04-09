<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubscriptionManagementFields extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('status');
            $table->timestamp('paused_at')->nullable()->after('is_paused');
            $table->timestamp('resumed_at')->nullable()->after('paused_at');
            $table->timestamp('canceled_at')->nullable()->after('resumed_at');
            $table->timestamp('grace_ends_at')->nullable()->after('canceled_at');
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'paused_at', 'resumed_at', 'canceled_at', 'grace_ends_at']);
        });
    }
}
