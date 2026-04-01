<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super-admin';
    case User = 'user';

    public function canDeleteIpRecords(): bool
    {
        return $this === self::SuperAdmin;
    }
}
