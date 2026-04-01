<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class GatewayRequestLog extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'correlation_id',
        'method',
        'path',
        'upstream',
        'actor_user_id',
        'actor_role',
        'response_status',
        'request_ip',
    ];
}
