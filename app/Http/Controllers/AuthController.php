<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $credentials['is_active'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'E-mail ou senha inválidos, ou conta desativada.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();
        if ($user->passwordMustBeChanged()) {
            return redirect()->route('password.required');
        }

        $expiresAt = $user->passwordExpiresAt();
        $destination = $user->role !== 'master'
            ? redirect()->route('portal.processes.index')
            : redirect()->intended(route('master.processes.index'));

        if ($expiresAt && $expiresAt->lte(now()->addDays(7))) {
            $daysLeft = max(1, (int) ceil((now()->diffInSeconds($expiresAt, false)) / 86400));
            $dayLabel = $daysLeft === 1 ? 'dia' : 'dias';
            $destination->with('password_expiry_warning', "Sua senha expira em {$daysLeft} {$dayLabel}. Altere-a em Meu perfil para evitar o bloqueio do acesso.");
        }

        return $destination;
    }

    public function requestPasswordReset(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => 'Aguarde alguns minutos antes de solicitar outro link de recuperação.',
            ])->onlyInput('email');
        }

        return back()->with('status', 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir sua senha.');
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => PasswordPolicy::rules(),
        ], PasswordPolicy::messages());

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Senha redefinida com sucesso. Você já pode entrar.');
        }

        return back()->withErrors([
            'email' => $status === Password::INVALID_TOKEN
                ? 'Este link de redefinição expirou ou já foi utilizado. Solicite um novo link.'
                : 'Não encontramos uma conta com esse e-mail.',
        ])->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
