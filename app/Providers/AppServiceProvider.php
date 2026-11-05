<?php

namespace App\Providers;

use App\Http\Controllers\ProcessTaskController;
use App\Services\FlowableClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('shared.portal-navigation', function ($view): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $data = $view->getData();
            $pendingCount = $data['pendingTaskCount'] ?? null;

            if (! is_numeric($pendingCount) && isset($data['tasks']) && ! ($data['unavailable'] ?? false)) {
                $pendingCount = $data['tasks']->count();
            }

            if (! is_numeric($pendingCount)) {
                try {
                    $pendingCount = Cache::remember(
                        'portal-pending-task-count:'.$user->id,
                        now()->addSeconds(30),
                        fn (): int => ProcessTaskController::pendingCountFor($user, app(FlowableClient::class)),
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                    $pendingCount = null;
                }
            }

            $view->with('portalPendingTaskCount', $pendingCount);
        });
    }
}
