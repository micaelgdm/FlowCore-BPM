<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'role' => $data['role'],
            'password' => $data['password'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Usuário criado. Informe a senha inicial diretamente ao titular da conta.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);

        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Não é possível alterar o próprio perfil ou desativar a própria conta.']);
        }

        $removingLastMaster = $user->role === UserRole::Master->value
            && $user->is_active
            && ($data['role'] !== UserRole::Master->value || ! (bool) $data['is_active'])
            && ! User::query()->where('role', UserRole::Master->value)->where('is_active', true)->where('id', '!=', $user->id)->exists();

        if ($removingLastMaster) {
            return back()->withErrors(['user' => 'A plataforma precisa manter ao menos um Administrador Master ativo.']);
        }

        $password = $data['password'] ?? null;
        unset($data['password']);
        $data['email'] = mb_strtolower($data['email']);

        $user->forceFill($data);
        if (filled($password)) {
            $user->password = $password;
        }
        $user->save();

        return back()->with('status', 'Cadastro do usuário atualizado.');
    }
}
