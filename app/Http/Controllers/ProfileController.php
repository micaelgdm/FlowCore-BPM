<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Informe sua senha atual.',
            'current_password.current_password' => 'A senha atual não confere.',
            'password.min' => 'A nova senha deve ter pelo menos :min caracteres.',
            'password.confirmed' => 'A confirmação da nova senha não confere.',
            'password.different' => 'A nova senha deve ser diferente da senha atual.',
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $request->session()->regenerate();

        return back()->with('status', 'Senha alterada com sucesso.');
    }
}
