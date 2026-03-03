<?php

namespace App\Http\Controllers;

use App\Models\FormCondition;
use App\Traits\AccessControl;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class FormConditionController extends Controller
{
    use AccessControl;

    public function store(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can manage conditions.',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'form_id' => 'required|exists:forms,id',
                'trigger_form_detail_id' => 'required|exists:form_details,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['message' => $validator->errors()->first()], 422);
            }

            $condition = FormCondition::create([
                'form_id' => (int)$request->input('form_id'),
                'trigger_form_detail_id' => (int)$request->input('trigger_form_detail_id'),
            ]);

            return response()->json([
                'message' => 'Condition created successfully.',
                'condition_id' => $condition->id,
            ], 201);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Database error occurred.'], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, FormCondition $condition)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can manage conditions.',
                ], 403);
            }

            $condition->delete();

            return response()->json(['message' => 'Condition deleted successfully.'], 200);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Database error occurred.'], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
