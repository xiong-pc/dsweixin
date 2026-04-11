<?php

/**
 * @Author: xiong-pc
 *
 * @Email: 562740366@qq.com
 *
 * @Date: 2026-04-10 12:00:00
 *
 * @Version: 1.0.0
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pid',
        'name',
        'shortname',
        'longitude',
        'latitude',
        'level',
        'sort',
        'status',
    ];

    protected $casts = [
        'pid' => 'integer',
        'level' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
    ];
}
