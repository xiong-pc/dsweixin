<?php

/**
 * @Author: xiong-pc
 *
 * @Email: 562740366@qq.com
 *
 * @Date: 2026-04-15 00:00:00
 *
 * @Version: 1.0.0
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'username',
        'site_id',
        'url',
        'data',
        'ip',
        'action_name',
    ];

    protected $casts = [
        'uid' => 'integer',
        'site_id' => 'integer',
    ];
}
