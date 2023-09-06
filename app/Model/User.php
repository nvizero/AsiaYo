<?php

declare (strict_types=1);

namespace App\Models;

use HyperfExt\Auth\Authenticatable;
use Hyperf\DbConnection\Model\Model;
use HyperfExt\Jwt\Contracts\JwtSubjectInterface;
use HyperfExt\Auth\Contracts\AuthenticatableInterface;

/**
 * @property int    $id
 * @property string $name
 * @property string $password
 * @property int    $sex
 * @property int    $age
 * @property string $email
 * @property string $phone
 * @property int    $status
 */
class User extends Model implements AuthenticatableInterface, JwtSubjectInterface
{
    use Authenticatable;

    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'users';
    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['name', 'sex', 'age', 'password'];
    /**
     * The attributes that should be cast to native types.
     * @var array
     */
    protected $casts = ['id' => 'integer', 'sex' => 'integer', 'age' => 'integer'];

    public function getJwtIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT自定义载荷
     * @return array
     */
    public function getJwtCustomClaims(): array
    {
        return [
            'guard' => 'api'    // 添加一个自定义载荷保存守护名称，方便后续判断
        ];
    }

    public const STATUS_NORMAL = 1;
    public const STATUS_DISABLE = 2;
    public const STATUS_DELETE = 3;
}
