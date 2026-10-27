<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['process_instance_id', 'flowable_task_id', 'task_name', 'task_definition_key', 'decided_by', 'decision', 'justification', 'form_data', 'flowable_completed_at'])]
class ProcessTaskDecision extends Model
{
    protected function casts(): array
    {
        return ['flowable_completed_at' => 'immutable_datetime', 'form_data' => 'array'];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
