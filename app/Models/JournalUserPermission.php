<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JournalUserPermission extends Model
{
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'journal_id',
        'user_id',
        'can_view',
        'can_post',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_post' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalUserPermission $permission) {
            if (empty($permission->id)) {
                $permission->id = (string) Str::uuid();
            }
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
