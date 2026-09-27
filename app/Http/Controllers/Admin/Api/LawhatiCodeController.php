<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\WhatsappGateway;
use Illuminate\Http\JsonResponse;

/**
 * Sign-in codes for لوحتي, the team's Kanban app at panel.<domain>/lawhati.
 * A code is a token that can only name its employee and list the team. An
 * employee has one code at a time: issuing a new one retires the previous
 * one on every device. The code is shown once and only its hash is kept.
 */
class LawhatiCodeController extends Controller
{
    public function store(Employee $employee, WhatsappGateway $whatsapp): JsonResponse
    {
        $employee->tokens()->where('name', Employee::LAWHATI_TOKEN)->delete();

        $code = $employee->createToken(Employee::LAWHATI_TOKEN, [Employee::LAWHATI_TOKEN])->plainTextToken;
        $link = 'https://panel.'.config('app.domain').'/lawhati/sign-in?code='.rawurlencode($code);
        $phone = $whatsapp->normalizePhone((string) $employee->phone);

        return response()->json([
            'data' => [
                'employee_id' => $employee->id,
                'code' => $code,
                'link' => $link,
                'whatsapp_url' => $phone === ''
                    ? null
                    : 'https://wa.me/'.$phone.'?text='.rawurlencode("رمز دخولك إلى لوحتي:\n".$link),
            ],
        ], 201);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $revoked = $employee->tokens()->where('name', Employee::LAWHATI_TOKEN)->delete();

        return response()->json(['data' => ['employee_id' => $employee->id, 'revoked' => $revoked]]);
    }
}
