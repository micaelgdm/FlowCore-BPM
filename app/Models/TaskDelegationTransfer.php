<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_task_delegation_id', 'process_instance_id', 'flowable_task_id', 'original_user_id', 'delegate_user_id', 'transferred_at', 'completed_at'])]
class TaskDelegationTransfer extends Model
{
    protected function casts(): array
    {
        return [
            'transferred_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(UserTaskDelegation::class, 'user_task_delegation_id');
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(ProcessTaskDecision::class, 'flowable_task_id', 'flowable_task_id');
    }
}
