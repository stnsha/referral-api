<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait AccessControl
{
    /**
     * Check if user is superadmin (referral = 1)
     *
     * @param array $jwtPayload JWT payload from request
     * @return bool
     */
    protected function isSuperadmin($jwtPayload)
    {
        return isset($jwtPayload['referral']) && $jwtPayload['referral'] === 1;
    }

    /**
     * Get referral level from JWT payload
     *
     * @param array $jwtPayload JWT payload from request
     * @return int Default to 2 (admin of dept) for backwards compatibility
     */
    protected function getReferralLevel($jwtPayload)
    {
        return $jwtPayload['referral'] ?? 2;
    }

    /**
     * Apply business unit filter to query if not superadmin
     *
     * @param Builder $query Eloquent query builder
     * @param array $jwtPayload JWT payload from request
     * @param string $column Column name to filter (default: 'business_unit_id')
     * @return Builder
     */
    protected function applyBusinessUnitFilter($query, $jwtPayload, $column = 'business_unit_id')
    {
        // Superadmin bypasses all filters
        if ($this->isSuperadmin($jwtPayload)) {
            return $query;
        }

        // Apply business_unit_id filter for admin and normal users
        $businessUnitId = $jwtPayload['business_unit_id'] ?? null;
        if ($businessUnitId) {
            $query->where($column, $businessUnitId);
        }

        return $query;
    }

    /**
     * Apply outlet filter to query if not superadmin
     *
     * @param Builder $query Eloquent query builder
     * @param array $jwtPayload JWT payload from request
     * @param string $column Column name to filter (default: 'location')
     * @return Builder
     */
    protected function applyOutletFilter($query, $jwtPayload, $column = 'location')
    {
        // Superadmin bypasses all filters
        if ($this->isSuperadmin($jwtPayload)) {
            return $query;
        }

        // Apply outlet filter for admin and normal users
        $listOutlets = $jwtPayload['outlet'] ?? null;
        if ($listOutlets && is_array($listOutlets)) {
            $query->whereIn($column, $listOutlets);
        }

        return $query;
    }

    /**
     * Check if user can access specific business unit
     * For superadmin: always true
     * For others: only their own business unit
     *
     * @param array $jwtPayload JWT payload from request
     * @param int $targetBusinessUnitId Business unit ID to check
     * @return bool
     */
    protected function canAccessBusinessUnit($jwtPayload, $targetBusinessUnitId)
    {
        // Superadmin can access all business units
        if ($this->isSuperadmin($jwtPayload)) {
            return true;
        }

        // Others can only access their own business unit
        $userBusinessUnitId = $jwtPayload['business_unit_id'] ?? null;
        return $userBusinessUnitId == $targetBusinessUnitId;
    }

    /**
     * Get business unit ID for filtering, or null for superadmin
     *
     * @param array $jwtPayload JWT payload from request
     * @return int|null
     */
    protected function getBusinessUnitIdForFilter($jwtPayload)
    {
        if ($this->isSuperadmin($jwtPayload)) {
            return null;  // No filtering for superadmin
        }

        return $jwtPayload['business_unit_id'] ?? null;
    }

    /**
     * Check if user is read-only (referral = 0)
     * Referral 0 users can view but cannot edit/delete
     *
     * @param array $jwtPayload JWT payload from request
     * @return bool
     */
    protected function isReadOnly($jwtPayload)
    {
        $referralLevel = $this->getReferralLevel($jwtPayload);
        return $referralLevel === 0;
    }

    /**
     * Check if user can perform write operations (create, update, delete)
     * Only referral levels 1 and 2 can perform write operations
     *
     * @param array $jwtPayload JWT payload from request
     * @return bool
     */
    protected function canWrite($jwtPayload)
    {
        return !$this->isReadOnly($jwtPayload);
    }
}
