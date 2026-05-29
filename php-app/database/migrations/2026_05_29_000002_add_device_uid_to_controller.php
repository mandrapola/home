<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('controller', function (Blueprint $table): void {
            $table->string('device_uid', 64)->nullable()->after('user_id');
            $table->unique('device_uid', 'uk_controller_device_uid');
        });

        DB::table('controller')
            ->whereNull('device_uid')
            ->where('discription', 'like', 'Registered from device_uid: %')
            ->orderBy('created_at')
            ->get(['id', 'discription'])
            ->each(function (object $controller): void {
                $deviceUid = trim(substr((string) $controller->discription, strlen('Registered from device_uid: ')));
                if ($deviceUid !== '') {
                    $deviceUid = mb_substr($deviceUid, 0, 64);
                    $alreadyAssigned = DB::table('controller')
                        ->where('device_uid', $deviceUid)
                        ->exists();
                    if ($alreadyAssigned) {
                        return;
                    }

                    DB::table('controller')
                        ->where('id', (string) $controller->id)
                        ->update(['device_uid' => $deviceUid]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('controller', function (Blueprint $table): void {
            $table->dropUnique('uk_controller_device_uid');
            $table->dropColumn('device_uid');
        });
    }
};
