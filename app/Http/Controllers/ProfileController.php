<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function photo(Request $request): BinaryFileResponse
    {
        $path = $request->user()->profile_photo_path;
        abort_unless(
            is_string($path)
                && str_starts_with($path, 'profile-photos/'.$request->user()->id.'/')
                && Storage::disk('local')->exists($path),
            404,
        );

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ], [
            'photo.required' => 'Selecione uma foto para enviar.',
            'photo.image' => 'O arquivo precisa ser uma imagem válida.',
            'photo.mimes' => 'Use uma imagem JPG, PNG ou WEBP.',
            'photo.max' => 'A foto deve ter no máximo 2 MB.',
            'photo.dimensions' => 'A imagem deve ter no máximo 2048 pixels de largura e altura.',
        ]);

        $user = $request->user();
        $oldPath = $user->profile_photo_path;
        $newPath = $data['photo']->store('profile-photos/'.$user->id, 'local');

        if (! $newPath) {
            return back()->withErrors(['photo' => 'Não foi possível salvar a foto. Tente novamente.']);
        }

        $user->forceFill(['profile_photo_path' => $newPath])->save();

        if (is_string($oldPath) && str_starts_with($oldPath, 'profile-photos/'.$user->id.'/')) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', 'Foto de perfil atualizada.');
    }

    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $path = $user->profile_photo_path;
        $user->forceFill(['profile_photo_path' => null])->save();

        if (is_string($path) && str_starts_with($path, 'profile-photos/'.$user->id.'/')) {
            Storage::disk('local')->delete($path);
        }

        return back()->with('status', 'Foto removida. O avatar com suas iniciais voltou a ser exibido.');
    }

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'taskDelegations' => $user->taskDelegations()->with('delegate')->latest('starts_at')->get(),
            'delegateOptions' => User::query()
                ->where('is_active', true)
                ->where('id', '<>', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [...PasswordPolicy::rules(), 'different:current_password'],
        ], [
            'current_password.required' => 'Informe sua senha atual.',
            'current_password.current_password' => 'A senha atual não confere.',
            'password.different' => 'A nova senha deve ser diferente da senha atual.',
            ...PasswordPolicy::messages(),
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();

        return back()->with('status', 'Senha alterada com sucesso.');
    }

    public function requiredPasswordForm(): View
    {
        return view('auth.change-required');
    }

    public function forcePasswordUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [...PasswordPolicy::rules(), 'different:current_password'],
        ], [
            'current_password.required' => 'Informe a senha atual.',
            'current_password.current_password' => 'A senha atual não confere.',
            'password.different' => 'A nova senha deve ser diferente da senha atual.',
            ...PasswordPolicy::messages(),
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
        $request->session()->regenerate();

        $destination = $request->user()->role === 'master'
            ? route('master.processes.index')
            : route('portal.processes.index');

        return redirect($destination)->with('status', 'Senha atualizada. Você já pode continuar usando o FlowCore.');
    }
}
