<?php

namespace App\Http\Controllers;

use App\Jobs\SendTaskAssignmentEmail;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationOutboxController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'sent', 'failed'])],
            'search' => ['nullable', 'string', 'max:160'],
        ]);
        $summary = DB::table('task_notification_outbox')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as queued', ['queued'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent', ['sent'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', ['failed'])
            ->first();
        $messages = DB::table('task_notification_outbox')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('request_code', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%")
                        ->orWhere('process_name', 'like', "%{$search}%")
                        ->orWhere('task_name', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('notifications.outbox', compact('messages', 'summary', 'filters'));
    }

    public function retry(int $notification): RedirectResponse
    {
        try {
            DB::transaction(function () use ($notification): void {
                $message = DB::table('task_notification_outbox')->where('id', $notification)->lockForUpdate()->first();
                abort_unless($message, 404);
                abort_unless($message->status === 'failed', 409, 'Somente notificações com falha podem ser reenviadas.');

                DB::table('task_notification_outbox')->where('id', $notification)->update([
                    'status' => 'queued',
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

                SendTaskAssignmentEmail::dispatch($notification);
            });
        } catch (QueryException $exception) {
            report($exception);

            return back()->withErrors(['notification' => 'Não foi possível colocar o e-mail novamente na fila. Tente mais tarde.']);
        }

        return back()->with('status', 'E-mail colocado novamente na fila.');
    }
}
