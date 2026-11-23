<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Valores padrão de uma conta nova. "ativo", "sector_id" e "store_id" não estão
     * em $fillable de propósito: só o painel de usuários muda esses campos (com forceFill),
     * assim ninguém altera o próprio setor ou loja pela tela de perfil.
     */
    protected $attributes = [
        'ativo' => true,
    ];

    public function tasks() {
        return $this->hasMany('App\Models\Task');
    }


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'ultimo_acesso_em' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Tarefas em que a pessoa é RESPONSÁVEL (tabela task_user).
     * O nome antigo foi mantido para não quebrar o que já usa esta relação.
     */
    public function tasksAsParticipant() {
        return $this->belongsToMany('App\Models\Task')->withTimestamps();
    }

    /**
     * Tarefas em que a pessoa é PARTICIPANTE (acompanha, comenta e autoriza).
     */
    public function participacoes()
    {
        return $this->belongsToMany(Task::class, 'task_participants')->withPivot('added_by')->withTimestamps();
    }

    /** Setor onde a pessoa trabalha (um só) */
    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    /** Loja onde a pessoa trabalha */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /** Equipamentos que estão com esta pessoa */
    public function equipamentos()
    {
        return $this->hasMany(Equipment::class);
    }

    public function accessGroups()
    {
        return $this->belongsToMany(AccessGroup::class, 'access_group_user')->withTimestamps();
    }

    public function scopeAtivos($consulta)
    {
        return $consulta->where('ativo', true);
    }

    public function temDoisFatores(): bool
    {
        return ! empty($this->two_factor_secret) && ! empty($this->two_factor_confirmed_at);
    }

    public function isAdministrator(): bool
    {
        return $this->accessGroups->contains(fn (AccessGroup $grupo) => $grupo->isAdministrator());
    }

    public function hasPermission(string $permissao): bool
    {
        return $this->accessGroups->contains(fn (AccessGroup $grupo) => $grupo->grants($permissao));
    }
}
