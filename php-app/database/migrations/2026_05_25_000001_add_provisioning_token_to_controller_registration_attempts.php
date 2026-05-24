<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('controller_registration_attempts', 'provisioning_token_hash')) {
            Schema::table('controller_registration_attempts', function (Blueprint $table): void {
                $table->char('provisioning_token_hash', 64)->nullable()->after('device_uid');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('controller_registration_attempts', 'provisioning_token_hash')) {
            Schema::table('controller_registration_attempts', function (Blueprint $table): void {
                $table->dropColumn('provisioning_token_hash');
            });
        }
    }
};
