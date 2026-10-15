<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('admin.login');
    }

public function login(Request $request)
{
    $credentials = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
    }

    $request->session()->regenerate();

    // Redirection selon le rôle
    if (Auth::user()->peutAccederBackOffice()) {
        return redirect()->intended(route('admin.dashboard'));
    }

    return redirect()->route('home')->with('success', 'Bienvenue ' . Auth::user()->nom . ' !');
}

public function logout(Request $request)
{
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
}
}
