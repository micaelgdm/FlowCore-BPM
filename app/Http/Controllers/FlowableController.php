<?php

namespace App\Http\Controllers;

use App\Services\FlowableClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class FlowableController extends Controller
{
    public function processDefinitions(FlowableClient $flowable): JsonResponse
    {
        try {
            $response = $flowable->processDefinitions();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'O motor de processos está indisponível no momento.',
            ], 503);
        }

        $definitions = collect($response['data'] ?? [])->map(fn (array $definition): array => [
            'id' => $definition['id'] ?? null,
            'key' => $definition['key'] ?? null,
            'name' => $definition['name'] ?? null,
            'version' => $definition['version'] ?? null,
            'deploymentId' => $definition['deploymentId'] ?? null,
        ])->values();

        return response()->json([
            'total' => $response['total'] ?? $definitions->count(),
            'data' => $definitions,
        ]);
    }
}
