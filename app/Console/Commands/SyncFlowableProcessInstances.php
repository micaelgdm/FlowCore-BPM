<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Services\FlowableClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class SyncFlowableProcessInstances extends Command
{
    protected $signature = 'flowcore:sync-process-instances {--limit=25 : Maximum number of instances checked in one run}';

    protected $description = 'Synchronize local running request statuses with Flowable runtime and history';

    public function handle(FlowableClient $flowable): int
    {
        $limit = max(1, min(250, (int) $this->option('limit')));
        $instances = ProcessInstance::query()
            ->where('status', 'running')
            ->whereNotNull('flowable_process_instance_id')
            ->orderByRaw('flowable_status_checked_at IS NULL DESC')
            ->orderBy('flowable_status_checked_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'flowable_process_instance_id']);
        $changed = 0;
        $checked = 0;

        foreach ($instances as $instance) {
            try {
                $engineStatus = $flowable->processInstanceStatus($instance->flowable_process_instance_id);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                report($exception);
                $this->error('O Flowable ficou indisponível durante a sincronização; os próximos ciclos tentarão novamente.');

                return self::FAILURE;
            }

            $values = ['flowable_status_checked_at' => now()];
            if (in_array($engineStatus, ['completed', 'cancelled'], true)) {
                $values['status'] = $engineStatus;
            }

            $updated = ProcessInstance::query()
                ->whereKey($instance->id)
                ->where('status', 'running')
                ->update($values);

            $checked += $updated;
            $changed += $updated && isset($values['status']) ? 1 : 0;
        }

        $this->info("Verificadas: {$checked}. Status final atualizado: {$changed}.");

        return self::SUCCESS;
    }
}
