<?php

namespace App\Services;

use App\Exceptions\FlowableDeploymentConflict;
use App\Exceptions\FlowablePublicationRejected;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlowableClient
{
    /** @return array<string, mixed> */
    public function processDefinitions(): array
    {
        return $this->client()
            ->get('repository/process-definitions')
            ->throw()
            ->json();
    }

    /** @return array<int, array<string, mixed>> */
    public function tasksForAssignee(string $assignee): array
    {
        $response = $this->client()->get('runtime/tasks', [
            'assignee' => $assignee,
            'size' => 100,
        ])->throw()->json();

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function tasksForCandidateGroup(string $groupId): array
    {
        $response = $this->client()->get('runtime/tasks', [
            'candidateGroup' => $groupId,
            'size' => 100,
        ])->throw()->json();

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function allActiveTasks(int $maximum = 5000): array
    {
        $tasks = [];
        $pageSize = 500;

        while (count($tasks) < $maximum) {
            $response = $this->client()->get('runtime/tasks', [
                'size' => min($pageSize, $maximum - count($tasks)),
                'start' => count($tasks),
            ])->throw()->json();
            $page = is_array($response['data'] ?? null) ? $response['data'] : [];
            $tasks = [...$tasks, ...$page];

            if (count($page) < $pageSize || $page === []) {
                break;
            }
        }

        return $tasks;
    }

    public function claimTask(string $taskId, string $assignee): bool
    {
        try {
            $this->client()->post("runtime/tasks/{$taskId}", [
                'action' => 'claim',
                'assignee' => $assignee,
            ])->throw();

            return true;
        } catch (RequestException $exception) {
            if ($exception->response->status() === 409) {
                return false;
            }

            throw $exception;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function tasksForProcessInstance(string $processInstanceId): array
    {
        $response = $this->client()->get('runtime/tasks', [
            'processInstanceId' => $processInstanceId,
            'size' => 100,
        ])->throw()->json();

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    public function assignUnassignedTasks(string $processInstanceId, string $assignee): void
    {
        foreach ($this->tasksForProcessInstance($processInstanceId) as $task) {
            if (! is_string($task['id'] ?? null) || filled($task['assignee'] ?? null)) {
                continue;
            }

            $this->client()->put("runtime/tasks/{$task['id']}", ['assignee' => $assignee])->throw();
        }
    }

    /** @return array<string, mixed>|null */
    public function task(string $taskId): ?array
    {
        try {
            return $this->client()->get("runtime/tasks/{$taskId}")->throw()->json();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function historicTask(string $taskId): ?array
    {
        try {
            return $this->client()->get("history/historic-task-instances/{$taskId}")->throw()->json();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /** @param array<int, array{name: string, type: string, value: mixed}> $variables */
    public function completeTask(string $taskId, array $variables): void
    {
        $this->client()->post("runtime/tasks/{$taskId}", [
            'action' => 'complete',
            'variables' => $variables,
        ])->throw();
    }

    public function processInstanceIsActive(string $processInstanceId): bool
    {
        return $this->processInstanceStatus($processInstanceId) === 'running';
    }

    /** Returns null when the engine cannot confirm the instance in runtime or history. */
    public function processInstanceStatus(string $processInstanceId): ?string
    {
        $client = $this->client();
        $runtime = $client->get('runtime/process-instances', ['id' => $processInstanceId])->throw()->json();

        if (($runtime['total'] ?? 0) > 0) {
            return 'running';
        }

        $history = $this->historicProcessInstance($processInstanceId);

        if (! is_array($history) || ! filled($history['endTime'] ?? null)) {
            return null;
        }

        return filled($history['deleteReason'] ?? null) ? 'cancelled' : 'completed';
    }

    /** @return array<string, mixed>|null */
    public function historicProcessInstance(string $processInstanceId): ?array
    {
        try {
            $history = $this->client()->get("history/historic-process-instances/{$processInstanceId}")->throw()->json();

            return is_array($history) ? $history : null;
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $formData
     *  @return array<string, string>
     */
    public function startProcessInstance(string $processDefinitionId, string $businessKey, array $formData): array
    {
        $client = $this->client();
        $existing = $client->get('runtime/process-instances', ['businessKey' => $businessKey])
            ->throw()
            ->json();
        $instance = $existing['data'][0] ?? null;

        if (! is_array($instance)) {
            $history = $client->get('history/historic-process-instances', ['businessKey' => $businessKey])
                ->throw()
                ->json();
            $instance = $history['data'][0] ?? null;
        }

        if (! is_array($instance)) {
            $variables = [];

            foreach ($formData as $name => $value) {
                $type = match (true) {
                    is_bool($value) => 'boolean',
                    is_int($value) => 'integer',
                    is_float($value) => 'double',
                    default => 'string',
                };
                $variables[] = ['name' => $name, 'type' => $type, 'value' => $value];
            }

            $instance = $client->post('runtime/process-instances', [
                'processDefinitionId' => $processDefinitionId,
                'businessKey' => $businessKey,
                'variables' => $variables,
            ])->throw()->json();
        }

        if (! is_string($instance['id'] ?? null)) {
            throw new RuntimeException('O Flowable não retornou o identificador da solicitação.');
        }

        return ['process_instance_id' => $instance['id']];
    }

    /** @return array<string, string> */
    public function deployBpmn(string $deploymentName, string $xml): array
    {
        $resourceName = "{$deploymentName}.bpmn20.xml";
        $client = $this->client();
        $existing = $client->get('repository/deployments', ['name' => $deploymentName])
            ->throw()
            ->json();

        if (($existing['total'] ?? 0) > 0) {
            $deployment = $existing['data'][0];
            $resources = $client->get("repository/deployments/{$deployment['id']}/resources")
                ->throw()
                ->json();
            $resource = collect($resources)->firstWhere('id', $resourceName);

            if ($resource === null) {
                throw new FlowableDeploymentConflict('Existing deployment has no matching BPMN resource.');
            }

            $storedXml = $client->get("repository/deployments/{$deployment['id']}/resourcedata/{$resourceName}")
                ->throw()
                ->body();

            if (! hash_equals(hash('sha256', $xml), hash('sha256', $storedXml))) {
                throw new FlowableDeploymentConflict('Existing deployment has different BPMN content.');
            }

            return $this->processDefinitionForDeployment($client, (string) $deployment['id']);
        }

        $deployment = $client
            ->attach('file', $xml, $resourceName, ['Content-Type' => 'text/xml'])
            ->post('repository/deployments')
            ->throw()
            ->json();

        if (! is_string($deployment['id'] ?? null)) {
            throw new RuntimeException('O Flowable não retornou o identificador da publicação.');
        }

        return $this->processDefinitionForDeployment($client, $deployment['id']);
    }

    public function deleteDeployment(string $deploymentId): void
    {
        try {
            $this->client()->delete("repository/deployments/{$deploymentId}")->throw();
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }
        }
    }

    private function processDefinitionForDeployment(PendingRequest $client, string $deploymentId): array
    {
        $definitions = $client->get('repository/process-definitions', ['deploymentId' => $deploymentId])
            ->throw()
            ->json();
        $definition = $definitions['data'][0] ?? null;

        if (! is_array($definition) || ! is_string($definition['id'] ?? null)) {
            throw new FlowablePublicationRejected('Flowable did not create an executable process definition.');
        }

        return [
            'deployment_id' => $deploymentId,
            'process_definition_id' => $definition['id'],
        ];
    }

    private function client(): PendingRequest
    {
        $url = (string) config('services.flowable.url');
        $username = (string) config('services.flowable.username');
        $password = (string) config('services.flowable.password');

        if ($url === '' || $username === '' || $password === '') {
            throw new ConnectionException('Flowable is not configured.');
        }

        return Http::baseUrl($url)
            ->withBasicAuth($username, $password)
            ->acceptJson()
            ->timeout((int) config('services.flowable.timeout', 5));
    }
}
