<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    use BelongsToTenant;

    public static function includeZeroTenantInScope(): bool
    {
        return true;
    }

    protected $fillable = [
        'tenant_id', 'title', 'type', 'level', 'content',
        'publisher_id', 'status', 'publish_time',
    ];

    protected function casts(): array
    {
        return [
            'publish_time' => 'datetime',
        ];
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }
}
