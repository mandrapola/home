<?php

declare(strict_types=1);

namespace App\Support\Billing;

final class SubscriptionSource
{
    public const SYSTEM_DEFAULT = 'system_default';
    public const USER_SELECT = 'user_select';
    public const PAYMENT = 'payment';
    public const ADMIN_MANUAL = 'admin_manual';

    /**
     * @return array<int,string>
     */
    public static function all(): array
    {
        return [
            self::SYSTEM_DEFAULT,
            self::USER_SELECT,
            self::PAYMENT,
            self::ADMIN_MANUAL,
        ];
    }
}

