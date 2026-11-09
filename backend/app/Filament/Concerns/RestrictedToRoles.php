<?php

namespace App\Filament\Concerns;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;

/**
 * Restrict a Filament resource/page to admin roles. Super admins always pass.
 * Set `protected static array $allowedRoles = [AdminRole::Support, ...]` (empty = super admin only).
 */
trait RestrictedToRoles
{
    public static function canAccess(array $parameters = []): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin && $admin->hasRole(...static::allowedRoles());
    }

    /** @return array<int, AdminRole> */
    protected static function allowedRoles(): array
    {
        return property_exists(static::class, 'allowedRoles') ? static::$allowedRoles : [];
    }
}
