<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Les 3 rôles du modèle de données : valeur en base => libellé affiché
    public const ROLES = [
        'citoyen'      => 'Citoyen',
        'gestionnaire' => 'Gestionnaire',
        'admin'        => 'Administrateur',
    ];

    protected $fillable = ['nom', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // le mot de passe est chiffré automatiquement
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGestionnaire(): bool
    {
        return $this->role === 'gestionnaire';
    }

    public function isCitoyen(): bool
    {
        return $this->role === 'citoyen';
    }

    // Gestionnaires et admins accèdent au back office
    public function peutAccederBackOffice(): bool
    {
        return in_array($this->role, ['gestionnaire', 'admin']);
    }

    public function libelleRole(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
        // Initiales pour l'avatar : "Gestionnaire Test" => "GT"
    public function initiales(): string
    {
        return collect(explode(' ', $this->nom))
            ->filter()
            ->take(2)
            ->map(fn ($mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->implode('');
    }

    // Couleur Tabler associée au rôle
    public function couleurRole(): string
    {
        return ['admin' => 'red', 'gestionnaire' => 'blue', 'citoyen' => 'green'][$this->role] ?? 'secondary';
    }
}