<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Auth\IdentityInterface;

class User extends Model implements IdentityInterface
{
    use HasFactory;

    /**
     * Название таблицы, связанной с моделью.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array
     */
    protected $fillable = [
        'login',
        'password',
        'name',
        'role'
    ];

    /**
     * Атрибуты, которые должны быть скрыты при сериализации.
     *
     * @var array
     */
    protected $hidden = [
        'password'
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Роли пользователей системы.
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_LIBRARIAN = 'librarian';

    /**
     * Получить все выдачи, оформленные этим пользователем (библиотекарем).
     */
    public function issuedBooks(): HasMany
    {
        return $this->hasMany(BookIssue::class, 'librarian_id');
    }

    /**
     * Получить активные выдачи, оформленные этим пользователем.
     */
    public function activeIssues()
    {
        return $this->issuedBooks()->where('status', 'issued');
    }

    /**
     * Получить завершенные выдачи, оформленные этим пользователем.
     */
    public function returnedIssues()
    {
        return $this->issuedBooks()->where('status', 'returned');
    }

    /**
     * Проверить, является ли пользователь администратором.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Проверить, является ли пользователь библиотекарем.
     *
     * @return bool
     */
    public function isLibrarian(): bool
    {
        return $this->role === self::ROLE_LIBRARIAN;
    }

    /**
     * Scope для поиска пользователей по роли.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $role
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope для поиска администраторов.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdmins($query)
    {
        return $query->byRole(self::ROLE_ADMIN);
    }

    /**
     * Scope для поиска библиотекарей.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLibrarians($query)
    {
        return $query->byRole(self::ROLE_LIBRARIAN);
    }

    /**
     * Scope для поиска пользователей по имени.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByName($query, $name)
    {
        return $query->where('name', 'like', '%' . $name . '%');
    }

    /**
     * Scope для поиска пользователей по логину.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $login
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByLogin($query, $login)
    {
        return $query->where('login', 'like', '%' . $login . '%');
    }

    /**
     * Mutator для хеширования пароля при установке.
     *
     * @param string $value
     * @return void
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = md5($value);
    }

    /**
     * Mutator для приведения логина к нижнему регистру.
     *
     * @param string $value
     * @return void
     */
    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = strtolower(trim($value));
    }

    /**
     * Mutator для установки роли по умолчанию.
     *
     * @param string $value
     * @return void
     */
    public function setRoleAttribute($value)
    {
        $this->attributes['role'] = $value ?: self::ROLE_LIBRARIAN;
    }

    /**
     * Accessor для отформатированного имени роли.
     *
     * @return string
     */
    public function getRoleNameAttribute(): string
    {
        return $this->role === self::ROLE_ADMIN ? 'Администратор' : 'Библиотекарь';
    }

    /**
     * Accessor для статистики по выдачам.
     *
     * @return array
     */
    public function getIssueStatsAttribute(): array
    {
        return [
            'total_issues' => $this->issuedBooks()->count(),
            'active_issues' => $this->activeIssues()->count(),
            'returned_issues' => $this->returnedIssues()->count()
        ];
    }

    /**
     * Получить список всех доступных ролей.
     *
     * @return array
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_LIBRARIAN => 'Библиотекарь'
        ];
    }

    /**
     * Проверить, может ли пользователь управлять другими пользователями.
     *
     * @return bool
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Проверить, может ли пользователь работать с книгами и читателями.
     *
     * @return bool
     */
    public function canManageLibrary(): bool
    {
        return $this->isAdmin() || $this->isLibrarian();
    }

    /**
     * Получить пользователя по идентификатору (для IdentityInterface).
     *
     * @param int $id
     * @return User|null
     */
    public function findIdentity(int $id)
    {
        return self::where('id', $id)->first();
    }

    /**
     * Получить идентификатор пользователя (для IdentityInterface).
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Попытка аутентификации пользователя (для IdentityInterface).
     *
     * @param array $credentials
     * @return User|null
     */
    public function attemptIdentity(array $credentials)
    {
        return self::where([
            'login' => $credentials['login'],
            'password' => md5($credentials['password'])
        ])->first();
    }

    /**
     * Создать нового пользователя с валидацией ролей.
     *
     * @param array $attributes
     * @return User
     * @throws \InvalidArgumentException
     */
    public static function createUser(array $attributes)
    {
        $role = $attributes['role'] ?? self::ROLE_LIBRARIAN;

        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_LIBRARIAN])) {
            throw new \InvalidArgumentException("Недопустимая роль пользователя: {$role}");
        }

        // Только администраторы могут создавать других администраторов
        if ($role === self::ROLE_ADMIN && !app()->auth::user()?->isAdmin()) {
            throw new \InvalidArgumentException("Недостаточно прав для создания администратора");
        }

        return self::create($attributes);
    }

    /**
     * Получить информацию о пользователе для отображения.
     *
     * @return array
     */
    public function getUserInfoAttribute(): array
    {
        return [
            'id' => $this->id,
            'login' => $this->login,
            'name' => $this->name,
            'role' => $this->role,
            'role_name' => $this->role_name,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'issues_stats' => $this->issue_stats
        ];
    }
}