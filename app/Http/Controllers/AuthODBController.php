<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use Illuminate\Http\Request;

class AuthODBController extends Controller
{
    public function auth(Request $request)
    {
        $validated = $request->validate([
            'staff_id'             => 'required|integer',
            'business_unit_id'     => 'nullable|integer',
            'status_semasa'        => 'required|string',
            'outlet'               => 'nullable|array',
            'outlet.*'             => 'integer',
            'referral'             => 'nullable|integer|in:0,1,2',
            'staff_department_id'  => 'nullable|integer',
        ]);

        $businessUnitId = $validated['business_unit_id'] ?? null;

        // The frontend never actually resolves a business_unit_id (the legacy
        // code that used to do it is dead/commented out), so it's always
        // null here in practice. Without one, non-elevated users hit a hard
        // 401 on every referral-scoped endpoint. Derive it server-side,
        // mirroring ODBController::mapToBusinessUnitId(): most business
        // units map by staff_department_id, a few (e.g. HQ) only have an
        // outlet_id, so fall back to matching the staff's outlet.
        if (!$businessUnitId && !empty($validated['staff_department_id'])) {
            $businessUnitId = $this->mapToBusinessUnitId($validated['staff_department_id'], $validated['status_semasa']);
        }

        if (!$businessUnitId && !empty($validated['outlet'])) {
            $businessUnit = BusinessUnit::whereIn('outlet_id', $validated['outlet'])
                ->where('is_active', 1)
                ->first();
            $businessUnitId = $businessUnit ? $businessUnit->id : null;
        }

        $payload = [
            'iss'              => config('app.url'),
            'iat'              => time(),
            'exp'              => time() + (60 * 60 * 24),
            'staff_id'         => $validated['staff_id'],
            'business_unit_id' => $businessUnitId,
            'status_semasa'    => $validated['status_semasa'],
            'outlet'           => $validated['outlet'] ?? [],
            'referral'         => $validated['referral'] ?? 0,
        ];

        $token = ODBController::generateJWT($payload);

        return response()->json([
            'token'      => $token,
            'expires_in' => 86400,
        ], 200);
    }

    /**
     * Mirrors ODBController::mapToBusinessUnitId() - most business units are
     * looked up by staff_department_id, except department 1 (Audiology vs
     * Pharmacy), which is split by job title.
     */
    private function mapToBusinessUnitId($staffDepartmentId, $statusSemasa)
    {
        if ($staffDepartmentId == 1) {
            $isAudiologist = in_array(strtolower($statusSemasa), [
                'junior audiologist',
                'audiologist',
                'senior audiologist',
                'audiologist in charge'
            ]);

            $businessUnit = $isAudiologist
                ? BusinessUnit::where('name', 'Alpro Audiology')->first()
                : BusinessUnit::where('name', 'Alpro Pharmacy')->first();
        } else {
            $businessUnit = BusinessUnit::where('staff_department_id', $staffDepartmentId)->first();
        }

        return $businessUnit ? $businessUnit->id : null;
    }
}
