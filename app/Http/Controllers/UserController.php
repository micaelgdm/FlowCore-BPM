<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($request, $data): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'role' => $data['role'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            AuditEvent::query()->create([
                'actor_id' => $request->user()->id,
                'actor_name' => mb_substr($request->user()->name, 0, 120),
                'action' => 'user.created',
                'subject' => 'Conta de usuário #'.$user->id,
                'details' => ['role' => $user->role, 'is_active' => $user->is_active],
            ]);
        });

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
        $previous = ['role' => $user->role, 'is_active' => $user->is_active];

        DB::transaction(function () use ($request, $user, $data, $password, $previous): void {
            $user->forceFill($data);
            if (filled($password)) {
                $user->password = $password;
            }
            $user->save();

            $next = ['role' => $user->role, 'is_active' => $user->is_active];
            AuditEvent::query()->create([
                'actor_id' => $request->user()->id,
                'actor_name' => mb_substr($request->user()->name, 0, 120),
                'action' => 'user.updated',
                'subject' => 'Conta de usuário #'.$user->id,
                'details' => [
                    'previous' => $previous,
                    'current' => $next,
                    'password_reset' => filled($password),
                ],
            ]);
        });

        return back()->with('status', 'Cadastro do usuário atualizado.');
    }
}
