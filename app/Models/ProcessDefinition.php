<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'status', 'is_active', 'version', 'family_uuid', 'bpmn_xml', 'form_schema', 'task_group_assignments', 'task_user_assignments', 'task_form_schemas', 'gateway_routes', 'flowable_deployment_id', 'flowable_process_definition_id', 'published_bpmn_hash', 'published_at', 'created_by', 'updated_by'])]
class ProcessDefinition extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $process): void {
            $process->family_uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'form_schema' => 'array',
            'task_group_assignments' => 'array',
            'task_user_assignments' => 'array',
            'task_form_schemas' => 'array',
            'gateway_routes' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'process_user_access')->withPivot(['can_view', 'can_start', 'can_approve']);
    }

    public function instances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProcessInstance::class);
    }
}
