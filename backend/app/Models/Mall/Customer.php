<?php

namespace App\Models\Mall;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * 商城前台客户（B2C 顾客实体），与后台 User 表完全独立。
 *
 * 后续 M09-PR35 会用 passport-customer guard：
 *   - HasApiTokens trait 提供 createToken / tokens 关系
 *   - guard 在 config/auth.php 配置（PR35 再加）
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $shop_id
 * @property int|null $group_id
 * @property string $email
 * @property string $phone
 * @property string $password
 * @property string $name
 * @property string $avatar
 * @property int $gender
 * @property string|null $birthday
 * @property string $locale
 * @property string $currency
 * @property int $status
 */
class Customer extends Model implements AuthenticatableContract, OAuthenticatable
{
    use Authenticatable;
    use Authorizable;
    use HasApiTokens;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'shop_id', 'group_id',
        'email', 'phone', 'password',
        'name', 'avatar', 'gender', 'birthday',
        'locale', 'currency', 'status',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password',
    ];

    protected $attributes = [
        'email' => '',
        'phone' => '',
        'password' => '',
        'name' => '',
        'avatar' => '',
        'gender' => 0,
        'locale' => 'zh-CN',
        'currency' => 'CNY',
        'status' => 1,
        'last_login_ip' => '',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'shop_id' => 'integer',
            'group_id' => 'integer',
            'gender' => 'integer',
            'birthday' => 'date',
            'status' => 'integer',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function defaultAddress(): ?CustomerAddress
    {
        $addr = $this->addresses()->where('is_default', 1)->first();

        return $addr instanceof CustomerAddress ? $addr : null;
    }
}
