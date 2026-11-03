<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\UserTaskDelegation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserTaskDelegationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'delegate_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
        ], [
            'delegate_user_id.required' => 'Selecione a pessoa substituta.',
            'delegate_user_id.exists' => 'A pessoa selecionada não está ativa.',
            'starts_at.required' => 'Informe quando a delegação começa.',
            'starts_at.date_format' => 'Informe uma data e hora inicial válidas.',
            'ends_at.required' => 'Informe quando a delegação termina.',
            'ends_at.date_format' => 'Informe uma data e hora final válidas.',
            'ends_at.after' => 'O fim da delegação deve ser posterior ao início.',
        ])->validate();

        if ((int) $data['delegate_user_id'] === (int) $request->user()->id) {
            throw ValidationException::withMessages(['delegate_user_id' => 'Escolha uma pessoa diferente de você.']);
        }

        $timezone = config('app.display_timezone');
        $startsAt = Carbon::createFromFormat('Y-m-d\\TH:i', $data['starts_at'], $timezone)->utc();
        $endsAt = Carbon::createFromFormat('Y-m-d\\TH:i', $data['ends_at'], $timezone)->utc();

        $overlapExists = $request->user()->taskDelegations()
            ->whereNull('cancelled_at')
            ->where('starts_at', '<=', $endsAt)
            ->where('ends_at', '>=', $startsAt)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages(['starts_at' => 'Você já tem outra delegação nesse período. Cancele ou ajuste a delegação existente.']);
        }

        $delegateIsAbsent = UserTaskDelegation::query()
            ->where('user_id', (int) $data['delegate_user_id'])
            ->whereNull('cancelled_at')
            ->where('starts_at', '<=', $endsAt)
            ->where('ends_at', '>=', $startsAt)
            ->exists();

        if ($delegateIsAbsent) {
            throw ValidationException::withMessages(['delegate_user_id' => 'Essa pessoa também está com uma substituição programada nesse período. Escolha outra pessoa para evitar uma cadeia de delegações.']);
        }

        $delegation = $request->user()->taskDelegations()->create([
            'delegate_user_id' => (int) $data['delegate_user_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $delegation->load('delegate');

        AuditEvent::query()->create([
            'actor_id' => $request->user()->id,
            'actor_name' => mb_substr($request->user()->name, 0, 120),
            'action' => 'Delegação de tarefas programada',
            'subject' => mb_substr($request->user()->name.' → '.$delegation->delegate->name, 0, 255),
            'details' => [
                'starts_at' => $startsAt->setTimezone($timezone)->format('d/m/Y H:i'),
                'ends_at' => $endsAt->setTimezone($timezone)->format('d/m/Y H:i'),
            ],
        ]);

        if ($startsAt->lte(now()) && $endsAt->gte(now())) {
            try {
                Artisan::call('flowcore:apply-task-delegations', ['--owner' => (string) $request->user()->id]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return back()->with('status', 'Delegação programada. Durante o período, as tarefas individuais novas e pendentes serão encaminhadas à pessoa substituta.');
    }

    public function cancel(Request $request, int $delegation): RedirectResponse
    {
        $taskDelegation = $request->user()->taskDelegations()
            ->with('delegate')
            ->whereKey($delegation)
            ->firstOrFail();

        if ($taskDelegation->cancelled_at === null) {
            $taskDelegation->forceFill(['cancelled_at' => now()])->save();

            AuditEvent::query()->create([
                'actor_id' => $request->user()->id,
                'actor_name' => mb_substr($request->user()->name, 0, 120),
                'action' => 'Delegação de tarefas cancelada',
                'subject' => mb_substr($request->user()->name.' → '.$taskDelegation->delegate->name, 0, 255),
                'details' => [
                    'delegation_id' => $taskDelegation->id,
                    'tasks_already_transferred_remain_with_delegate' => true,
                ],
            ]);
        }

        return back()->with('status', 'Delegação encerrada. Novas tarefas voltarão a ser encaminhadas a você; tarefas já recebidas pela pessoa substituta permanecem com ela até a conclusão.');
    }
}
