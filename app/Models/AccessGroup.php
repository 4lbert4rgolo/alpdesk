<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccessGroup extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_group_user')->withTimestamps();
    }

    /**
     * O grupo Administrador sempre tem todas as permissões, inclusive as que
     * forem criadas no futuro.
     */
    public function isAdministrator(): bool
    {
        return $this->slug === 'administrador';
    }

    public function grants(string $permissao): bool
    {
        return $this->isAdministrator() || in_array($permissao, $this->permissions ?? [], true);
    }
}
