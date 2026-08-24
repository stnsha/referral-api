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
     * Check if user is HQ Admin (referral = 2): same data access as SuperAdmin
     * everywhere except the Admin Panel (form/field management), which stays
     * SuperAdmin-only (see isSuperadmin() call sites in FormController,
     * FormDetailsController, FormConditionController).
     *
     * @param array $jwtPayload JWT payload from request
     * @return bool
     */
    protected function isHqAdmin($jwtPayload)
    {
        return isset($jwtPayload['referral']) && $jwtPayload['referral'] === 2;
    }

    /**
     * Check if user has SuperAdmin-equivalent data access (SuperAdmin or HQ Admin).
     * Use this (not isSuperadmin()) for business-unit/outlet scoping bypass.
     *
     * @param array $jwtPayload JWT payload from request
     * @return bool
     */
    protected function isElevated($jwtPayload)
    {
        return $this->isSuperadmin($jwtPayload) || $this->isHqAdmin($jwtPayload);
    }

    /**
     * Get referral level from JWT payload
     *
     * @param array $jwtPayload JWT payload from request
     * @return int Default to 0 (normal user)
     */
    protected function getReferralLevel($jwtPayload)
    {
        return $jwtPayload['referral'] ?? 0;
    }

    /**
     * Apply business unit filter to query if not elevated (SuperAdmin/HQ Admin)
     *
     * @param Builder $query Eloquent query builder
     * @param array $jwtPayload JWT payload from request
     * @param string $column Column name to filter (default: 'business_unit_id')
     * @return Builder
     */
    protected function applyBusinessUnitFilter($query, $jwtPayload, $column = 'business_unit_id')
    {
        // SuperAdmin/HQ Admin bypass all filters
        if ($this->isElevated($jwtPayload)) {
            return $query;
        }

        // Apply business_unit_id filter for normal users
        $businessUnitId = $jwtPayload['business_unit_id'] ?? null;
        if ($businessUnitId) {
            $query->where($column, $businessUnitId);
        }

        return $query;
    }

    /**
     * Apply outlet filter to query if not elevated (SuperAdmin/HQ Admin)
     *
     * @param Builder $query Eloquent query builder
     * @param array $jwtPayload JWT payload from request
     * @param string $column Column name to filter (default: 'location')
     * @return Builder
     */
    protected function applyOutletFilter($query, $jwtPayload, $column = 'location')
    {
        // SuperAdmin/HQ Admin bypass all filters
        if ($this->isElevated($jwtPayload)) {
            return $query;
        }

        // Apply outlet filter for normal users
        $listOutlets = $jwtPayload['outlet'] ?? null;
        if ($listOutlets && is_array($listOutlets)) {
            $query->whereIn($column, $listOutlets);
        }

        return $query;
    }

    /**
     * Check if user can access specific business unit
     * For SuperAdmin/HQ Admin: always true
     * For others: only their own business unit
     *
     * @param array $jwtPayload JWT payload from request
     * @param int $targetBusinessUnitId Business unit ID to check
     * @return bool
     */
    protected function canAccessBusinessUnit($jwtPayload, $targetBusinessUnitId)
    {
        // SuperAdmin/HQ Admin can access all business units
        if ($this->isElevated($jwtPayload)) {
            return true;
        }

        // Others can only access their own business unit
        $userBusinessUnitId = $jwtPayload['business_unit_id'] ?? null;
        return $userBusinessUnitId == $targetBusinessUnitId;
    }

    /**
     * Get business unit ID for filtering, or null for SuperAdmin/HQ Admin
     *
     * @param array $jwtPayload JWT payload from request
     * @return int|null
     */
    protected function getBusinessUnitIdForFilter($jwtPayload)
    {
        if ($this->isElevated($jwtPayload)) {
            return null;  // No filtering for SuperAdmin/HQ Admin
        }

        return $jwtPayload['business_unit_id'] ?? null;
    }

}
