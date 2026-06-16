<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        
        // Track last login for "new since last login" notifications
        $request->session()->put('previous_login_at', $user->last_login_at);
        $user->update(['last_login_at' => now()]);

        if ($user->hasRole('admin')) {
            return redirect()->intended(route('dashboard', absolute: false));
        } elseif ($user->hasRole('owner') || $user->hasRole('company')) {
            return redirect()->intended(route('dashboard', absolute: false));
        } elseif ($user->hasRole('housekeeper')) {
            return redirect()->intended(route('sessions.index', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
