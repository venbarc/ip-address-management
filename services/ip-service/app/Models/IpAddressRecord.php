<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class IpAddressRecord extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ip_addresses';

    protected $fillable = [
        'address',
        'normalized_address',
        'version',
        'label',
        'comment',
        'created_by_user_id',
        'created_by_name',
        'created_by_email',
        'updated_by_user_id',
        'updated_by_name',
        'updated_by_email',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'version' => $this->version,
            'label' => $this->label,
            'comment' => $this->comment,
            'created_by_user_id' => $this->created_by_user_id,
            'created_by_name' => $this->created_by_name,
            'created_by_email' => $this->created_by_email,
            'updated_by_user_id' => $this->updated_by_user_id,
            'updated_by_name' => $this->updated_by_name,
            'updated_by_email' => $this->updated_by_email,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
