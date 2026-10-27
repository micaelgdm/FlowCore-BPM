<?php

namespace App\Http\Controllers;

use App\Models\ProcessGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcessGroupController extends Controller
{
    public function index(): View
    {
        return view('groups.index', [
            'groups' => ProcessGroup::query()->with('users')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:process_groups,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $group = ProcessGroup::query()->create([
            'name' => trim($data['name']),
            'flowable_group_id' => 'flowcore_group_' . bin2hex(random_bytes(12)),
            'description' => isset($data['description']) ? trim($data['description']) : null,
        ]);

        return redirect()->route('master.groups.index')->with('status', "Grupo {$group->name} criado.");
    }

    public function update(Request $request, ProcessGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('process_groups', 'name')->ignore($group->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'users' => ['nullable', 'array', 'max:500'],
            'users.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ]);

        DB::transaction(function () use ($group, $data): void {
            $group->forceFill([
                'name' => trim($data['name']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
            ])->save();

            $group->users()->sync($data['users'] ?? []);
        });

        return redirect()->route('master.groups.index')->with('status', "Grupo {$group->name} atualizado.");
    }

    public function destroy(ProcessGroup $group): RedirectResponse
    {
        $name = $group->name;
        $group->delete();

        return redirect()->route('master.groups.index')->with('status', "Grupo {$name} removido.");
    }
}
