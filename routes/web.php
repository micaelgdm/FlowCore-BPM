<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\FlowableController;
use App\Http\Controllers\NotificationOutboxController;
use App\Http\Controllers\PortalProcessController;
use App\Http\Controllers\ProcessAccessController;
use App\Http\Controllers\ProcessDefinitionController;
use App\Http\Controllers\ProcessFormController;
use App\Http\Controllers\ProcessGroupController;
use App\Http\Controllers\ProcessTaskController;
use App\Http\Controllers\ProcessTaskAssignmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserTaskDelegationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if (! $request->user()) {
        return redirect()->route('login');
    }

    return $request->user()->role === 'master'
        ? redirect()->route('master.processes.index')
        : redirect()->route('portal.processes.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'requestPasswordReset'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/password/change-required', [ProfileController::class, 'requiredPasswordForm'])->name('password.required');
    Route::put('/password/change-required', [ProfileController::class, 'forcePasswordUpdate'])->middleware('throttle:5,1')->name('password.required.update');
});

Route::middleware(['auth', 'active', 'password.current'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->middleware('throttle:10,1')->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/task-delegations', [UserTaskDelegationController::class, 'store'])->name('profile.task-delegations.store');
    Route::delete('/profile/task-delegations/{delegation}', [UserTaskDelegationController::class, 'cancel'])->whereNumber('delegation')->name('profile.task-delegations.cancel');
});

Route::middleware(['auth', 'active', 'password.current'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/processes', [PortalProcessController::class, 'index'])->name('processes.index');
    Route::get('/requests', [PortalProcessController::class, 'requestsIndex'])->name('requests.index');
    Route::get('/processes/{process}/start', [PortalProcessController::class, 'startForm'])->name('processes.start');
    Route::post('/processes/{process}/start', [PortalProcessController::class, 'start'])->name('processes.start.submit');
    Route::get('/requests/{uuid}', [PortalProcessController::class, 'show'])->name('requests.show');
    Route::get('/requests/{uuid}/attachments/{attachmentId}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::get('/tasks', [ProcessTaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/{taskId}/approve', [ProcessTaskController::class, 'approve'])->name('tasks.approve');
});

Route::middleware(['auth', 'master', 'password.current'])->prefix('master')->name('master.')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/notifications', [NotificationOutboxController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/retry', [NotificationOutboxController::class, 'retry'])->whereNumber('notification')->name('notifications.retry');
    Route::get('/audit', [AuditEventController::class, 'index'])->name('audit.index');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::get('/groups', [ProcessGroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [ProcessGroupController::class, 'store'])->name('groups.store');
    Route::put('/groups/{group}', [ProcessGroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [ProcessGroupController::class, 'destroy'])->name('groups.destroy');
    Route::get('/engine/process-definitions', [FlowableController::class, 'processDefinitions'])->name('engine.process-definitions');
    Route::get('/processes', [ProcessDefinitionController::class, 'index'])->name('processes.index');
    Route::post('/processes', [ProcessDefinitionController::class, 'store'])->name('processes.store');
    Route::delete('/processes/{process}', [ProcessDefinitionController::class, 'destroy'])->name('processes.destroy');
    Route::post('/processes/{process}/versions', [ProcessDefinitionController::class, 'newVersion'])->name('processes.versions.store');
    Route::delete('/processes/{process}/versions', [ProcessDefinitionController::class, 'discardVersion'])->name('processes.versions.destroy');
    Route::post('/processes/{process}/activate', [ProcessDefinitionController::class, 'activate'])->name('processes.activate');
    Route::get('/processes/{process}/editor', [ProcessDefinitionController::class, 'edit'])->name('processes.editor');
    Route::get('/processes/{process}/form', [ProcessFormController::class, 'edit'])->name('processes.form.edit');
    Route::put('/processes/{process}/form', [ProcessFormController::class, 'update'])->name('processes.form.update');
    Route::get('/processes/{process}/tasks', [ProcessTaskAssignmentController::class, 'edit'])->name('processes.tasks.edit');
    Route::put('/processes/{process}/tasks', [ProcessTaskAssignmentController::class, 'update'])->name('processes.tasks.update');
    Route::put('/processes/{process}/diagram', [ProcessDefinitionController::class, 'updateDiagram'])->name('processes.diagram.update');
    Route::post('/processes/{process}/publish', [ProcessDefinitionController::class, 'publish'])->name('processes.publish');
    Route::get('/processes/{process}/access', [ProcessAccessController::class, 'edit'])->name('processes.access.edit');
    Route::put('/processes/{process}/access', [ProcessAccessController::class, 'update'])->name('processes.access.update');
});
