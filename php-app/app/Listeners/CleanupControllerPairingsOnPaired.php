<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ControllerPaired;
use Illuminate\Support\Facades\DB;

class CleanupControllerPairingsOnPaired
{
    public function handle(ControllerPaired $event): void
    {
        DB::table('controller_pairings')
            ->whereIn('controller_id', function ($query) {
                $query->select('controller_id')
                    ->from('controller_user')
                    ->distinct();
            })
            ->orWhere('status', 'claimed')
            ->orWhere(function ($query): void {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            })
            ->delete();
    }
}
