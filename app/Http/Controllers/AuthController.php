<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LabAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt(array_merge($credentials, ['is_active' => true]), $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            LabAudit::log('User Login', 'Authentication', $user->email, "Signed in as {$user->role}", $user->name);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Invalid email, password, or inactive account.',
        ])->onlyInput('email');
    }

    public function switchRole(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $targetUser = User::where('email', $request->email)->where('is_active', true)->first();
        if ($targetUser) {
            Auth::login($targetUser);
            $request->session()->regenerate();
            LabAudit::log('Role Switched', 'Authentication', $targetUser->email, "Switched role to {$targetUser->role}", $targetUser->name);

            return redirect()->route('dashboard')->with('success', "Active Session switched to {$targetUser->name} ({$targetUser->role}). Navigation and permissions updated.");
        }

        return back()->with('error', 'Unable to switch to requested account.');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            LabAudit::log('User Logout', 'Authentication', $user->email, 'Signed out of PathoLab LIS', $user->name);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out.');
    }
}
