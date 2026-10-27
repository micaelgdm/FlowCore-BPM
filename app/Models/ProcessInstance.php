<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'idempotency_key', 'process_definition_id', 'started_by', 'flowable_process_instance_id', 'status', 'form_data'])]
class ProcessInstance extends Model
{
    protected function casts(): array
    {
        return ['form_data' => 'array'];
    }

    public function processDefinition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ProcessTaskDecision::class);
    }
}
