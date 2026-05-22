<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

class UserActivityService
{
    public function recordControllerReport(string $controllerId): void
    {
        $userIds = DB::table('controller_user')
            ->where('controller_id', $controllerId)
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $now = now();
        $timestamp = $now->toDateTimeString();
        $date = $now->toDateString();

        foreach ($userIds as $userId) {
            DB::statement(
                'INSERT INTO user_activity_days '
                . '(user_id, activity_date, first_seen_at, last_seen_at, reports_count, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, 1, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at), '
                . 'reports_count = reports_count + 1, updated_at = VALUES(updated_at)',
                [$userId, $date, $timestamp, $timestamp, $timestamp, $timestamp]
            );
        }
    }
}
