<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'flowable_group_id', 'description'])]
class ProcessGroup extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'process_group_user');
    }
}
