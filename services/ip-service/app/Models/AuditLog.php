<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'category',
        'event',
        'actor_user_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'session_uuid',
        'subject_type',
        'subject_id',
        'changes',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are immutable.');
        });
    }
}
