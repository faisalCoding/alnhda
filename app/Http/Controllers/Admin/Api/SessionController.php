<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        return response()->json([
            'authenticated' => (bool) $admin,
            'csrf' => $request->session()->token(),
            'admin' => $admin ? ['name' => $admin->name, 'email' => $admin->email] : null,
        ]);
    }
}
