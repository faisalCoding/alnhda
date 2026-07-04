<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminRegisterRequest;
use App\Models\Admin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredAdminController extends Controller
{
    public function create(): View
    {
        abort_unless(config('app.admin_registration_enabled'), 404);

        return view('admin.auth.register');
    }

    public function store(AdminRegisterRequest $request): RedirectResponse
    {
        abort_unless(config('app.admin_registration_enabled'), 404);

        $admin = Admin::query()->create($request->validated());

        event(new Registered($admin));

        Auth::guard('admin')->login($admin);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
