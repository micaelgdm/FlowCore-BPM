<?php

namespace App\Http\Controllers;

use App\Models\ProcessInstance;
use App\Models\User;
use App\Services\FlowableClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

class AttachmentController extends Controller
{
    public function download(Request $request, string $uuid, string $attachmentId, FlowableClient $flowable): StreamedResponse
    {
        $instance = ProcessInstance::query()
            ->with(['processDefinition.authorizedUsers', 'decisions'])
            ->where('uuid', $uuid)
            ->firstOrFail();
        $user = $request->user();
        abort_unless($this->canViewAttachments($instance, $user, $flowable), 403);

        $dataSets = [$instance->form_data ?? [], ...$instance->decisions->map(fn ($decision) => $decision->form_data ?? [])->all()];
        $attachment = null;
        foreach ($dataSets as $data) {
            foreach ($data as $value) {
                if (is_array($value) && ($value['attachment_id'] ?? null) === $attachmentId) {
                    $attachment = $value;
                    break 2;
                }
            }
        }

        abort_unless(is_array($attachment) && is_string($attachment['path'] ?? null), 404);
        abort_unless(Storage::disk('local')->exists($attachment['path']), 404);

        return Storage::disk('local')->download(
            $attachment['path'],
            basename((string) ($attachment['name'] ?? 'anexo')),
            ['Content-Type' => $attachment['mime'] ?? 'application/octet-stream'],
        );
    }

    private function canViewAttachments(ProcessInstance $instance, User $user, FlowableClient $flowable): bool
    {
        if ($user->role === 'master' || $instance->started_by === $user->id) {
            return true;
        }

        if ($instance->processDefinition->authorizedUsers->contains(fn (User $authorized): bool =>
            $authorized->id === $user->id && ((bool) $authorized->pivot->can_view || (bool) $authorized->pivot->can_approve))) {
            return true;
        }

        if ($instance->decisions->contains(fn ($decision): bool => $decision->decided_by === $user->id)) {
            return true;
        }

        if (blank($instance->flowable_process_instance_id)) {
            return false;
        }

        try {
            return collect($flowable->tasksForProcessInstance($instance->flowable_process_instance_id))
                ->contains(fn (array $task): bool => ProcessTaskController::userCanAccessTask($instance, $task, $user));
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            report($exception);

            return false;
        }
    }
}
