<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->user() && $request->user()->is_superadmin) {
            return redirect()->intended('/admin');
        }

        return redirect()->intended('/dashboard');
    }
}
