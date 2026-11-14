<?php

declare(strict_types=1);

namespace App\Modules\Access\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        $email = mb_strtolower(trim($data['email']));

        $key = 'login:'.hash(
            'sha256',
            $email.'|'.$request->ip()
        );

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Intenta nuevamente en {$seconds} segundos.",
            ]);
        }

        if (! Auth::guard('web')->attempt([
            'email' => $email,
            'password' => $data['password'],
            'active' => true,
        ])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'El correo o la contraseña no son correctos.',
            ]);
        }

        RateLimiter::clear($key);

        $request->session()->regenerate();

        $request->user()?->forceFill(['last_login_at' => now()])->save();

        // Discard legacy HTTP or external intended URLs saved before proxy
        // configuration was fixed. Preserve same-host paths and query strings.
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended)) {
            $parts = parse_url($intended);

            if (is_array($parts)
                && isset($parts['host'])
                && strcasecmp($parts['host'], $request->getHost()) === 0
                && in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                && ! isset($parts['user']) && ! isset($parts['pass'])) {
                return redirect()->to(
                    $request->getSchemeAndHttpHost()
                    .($parts['path'] ?? '/')
                    .(isset($parts['query']) ? '?'.$parts['query'] : '')
                );
            }
        }

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
