<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shared identity-protection check for any "quick fix a patient's name"
 * action (Test Report Dashboard, Diagnostic Invoice list, ...). A patient
 * is protected from this kind of direct rename if their name exactly
 * matches a Member/Admin/Supervisor staff account (by shared mobile
 * number) -- their own name, or one of that account's registered family
 * members (family_member_1/2/3) -- since renaming it here would desync it
 * from the linked staff identity. Broader than
 * DiagnosticInvoiceController::MEMBER_TIER_ROLES (which only covers the
 * member-discount check and excludes Admin).
 */
class PatientIdentityGuard
{
    private const PROTECTED_ROLES = ['Member', 'Admin', 'Supervisor'];

    public function protectedUsersForMobiles($mobiles): Collection
    {
        $mobiles = collect($mobiles)->filter()->unique()->values();

        if ($mobiles->isEmpty()) {
            return collect();
        }

        return User::whereIn('mobile_no', $mobiles)
            ->whereIn('role', self::PROTECTED_ROLES)
            ->get(['mobile_no', 'name', 'family_member_1', 'family_member_2', 'family_member_3']);
    }

    public function isProtected(?string $mobile, ?string $name, Collection $protectedUsers): bool
    {
        $normalizedName = strtolower(trim($name ?? ''));

        if ($normalizedName === '' || empty($mobile)) {
            return false;
        }

        return $protectedUsers
            ->where('mobile_no', trim($mobile))
            ->contains(function ($u) use ($normalizedName) {
                return in_array($normalizedName, array_filter([
                    strtolower(trim($u->name)),
                    strtolower(trim($u->family_member_1 ?? '')),
                    strtolower(trim($u->family_member_2 ?? '')),
                    strtolower(trim($u->family_member_3 ?? '')),
                ]), true);
            });
    }
}
