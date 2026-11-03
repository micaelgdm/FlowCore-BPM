<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['process_instance_id', 'flowable_task_id', 'original_assignee', 'escalated_to_user_id', 'transferred_at', 'completed_at'])]
class TaskEscalationTransfer extends Model
{
    protected function casts(): array
    {
        return [
            'transferred_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
    }
}
