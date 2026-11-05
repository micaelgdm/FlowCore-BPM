<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'profile_photo_path', 'must_change_password', 'password_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    protected $attributes = [
        'role' => 'user',
        'is_active' => true,
        'must_change_password' => false,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('password') && ! $user->isDirty('password_changed_at')) {
                $user->password_changed_at = now();
            }
        });
    }

    public function accessibleProcesses(): BelongsToMany
    {
        return $this->belongsToMany(ProcessDefinition::class, 'process_user_access')->withPivot(['can_view', 'can_start', 'can_approve']);
    }

    public function processGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProcessGroup::class, 'process_group_user');
    }

    public function initiatedProcessInstances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class, 'started_by');
    }

    public function taskDelegations(): HasMany
    {
        return $this->hasMany(UserTaskDelegation::class);
    }

    public function delegatedTaskTransfers(): HasMany
    {
        return $this->hasMany(TaskDelegationTransfer::class, 'delegate_user_id');
    }

    public function getRoleLabelAttribute(): string
    {
        return UserRole::tryFrom($this->role)?->label() ?? $this->role;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function passwordExpiresAt(): ?Carbon
    {
        return $this->password_changed_at?->copy()->addDays(90);
    }

    public function passwordMustBeChanged(): bool
    {
        $expiresAt = $this->passwordExpiresAt();

        return $this->must_change_password || ! $expiresAt || $expiresAt->isPast();
    }
}
