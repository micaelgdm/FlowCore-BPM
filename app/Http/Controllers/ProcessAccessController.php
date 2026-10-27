<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\ProcessDefinition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProcessAccessController extends Controller
{
    public function edit(ProcessDefinition $process): View
    {
        abort_unless($process->status === 'published', 409, 'Publique o processo antes de configurar quem pode acessá-lo.');

        $users = User::query()
            ->where('is_active', true)
            ->where('role', '!=', UserRole::Master->value)
            ->orderBy('name')
            ->get();

        return view('processes.access', [
            'process' => $process,
            'users' => $users,
            'permissions' => $process->authorizedUsers()
                ->get(['users.id'])
                ->mapWithKeys(fn (User $user): array => [$user->id => [
                    'can_view' => (bool) $user->pivot->can_view,
                    'can_start' => (bool) $user->pivot->can_start,
                ]])
                ->all(),
        ]);
    }

    public function update(Request $request, ProcessDefinition $process): RedirectResponse
    {
        abort_unless($process->status === 'published', 409, 'Publique o processo antes de configurar quem pode acessá-lo.');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.can_view' => ['nullable', 'boolean'],
            'permissions.*.can_start' => ['nullable', 'boolean'],
        ]);

        $permissions = $data['permissions'] ?? [];
        $userIds = array_map('intval', array_keys($permissions));
        $eligibleUsers = User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->where('role', '!=', UserRole::Master->value)
            ->get()
            ->keyBy('id');

        if ($eligibleUsers->count() !== count($userIds)) {
            return back()->withErrors(['permissions' => 'Selecione somente contas ativas que não sejam Master.']);
        }

        // Keep old process-level approver grants for running legacy instances.
        // New process approvals are routed by task groups, not by user profile.
        $existingPermissions = $process->authorizedUsers()
            ->get(['users.id', 'users.name'])
            ->mapWithKeys(fn (User $user): array => [$user->id => [
                'name' => $user->name,
                'can_view' => (bool) $user->pivot->can_view,
                'can_start' => (bool) $user->pivot->can_start,
            ]])
            ->all();
        $legacyApprovals = $process->authorizedUsers()
            ->wherePivot('can_approve', true)
            ->get(['users.id'])
            ->mapWithKeys(fn (User $user): array => [$user->id => true]);
        $sync = [];

        foreach ($permissions as $userId => $values) {
            $user = $eligibleUsers->get((int) $userId);
            $canView = (bool) ($values['can_view'] ?? false);
            $canStart = (bool) ($values['can_start'] ?? false);
            $canApprove = (bool) $legacyApprovals->get((int) $userId, false);

            if ($canStart || $canApprove) {
                $canView = true;
            }

            if ($canView || $canStart || $canApprove) {
                $sync[(int) $userId] = [
                    'can_view' => $canView,
                    'can_start' => $canStart,
                    'can_approve' => $canApprove,
                ];
            }
        }

        $nextAuditPermissions = collect($sync)->mapWithKeys(function (array $pivot, int|string $userId) use ($eligibleUsers): array {
            return [$userId => [
                'name' => $eligibleUsers->get((int) $userId)?->name ?? 'Usuário removido',
                'can_view' => $pivot['can_view'],
                'can_start' => $pivot['can_start'],
            ]];
        })->all();
        ksort($existingPermissions);
        ksort($nextAuditPermissions);

        DB::transaction(function () use ($process, $request, $sync, $existingPermissions, $nextAuditPermissions): void {
            $process->authorizedUsers()->sync($sync);

            if ($existingPermissions !== $nextAuditPermissions) {
                AuditEvent::query()->create([
                    'actor_id' => $request->user()->id,
                    'actor_name' => $request->user()->name,
                    'action' => 'process.access.updated',
                    'subject' => "{$process->name} · V{$process->version}",
                    'details' => ['previous_permissions' => $existingPermissions, 'permissions' => $nextAuditPermissions],
                ]);
            }
        });

        return back()->with('status', 'Permissões do processo atualizadas.');
    }
}
