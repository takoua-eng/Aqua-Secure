<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

// MustVerifyEmail : l'email doit être vérifié (lien reçu par email) avant d'accéder aux pages protégées
// TwoFactorAuthenticatable : authentification à deux facteurs (Fortify)
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    // Les 3 rôles du modèle de données : valeur en base => libellé affiché
    public const ROLES = [
        'citoyen'      => 'Citoyen',
        'gestionnaire' => 'Gestionnaire',
        'admin'        => 'Administrateur',
    ];

    protected $fillable = ['nom', 'email', 'password', 'role', 'photo'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
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

    // Adresse de la photo de profil (null si l'utilisateur n'en a pas : on affiche les initiales)
    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    // Zone d'habitation (citoyen) : il est prévenu des coupures programmées dans cette zone.
    // zone_id n'est pas dans $fillable : il ne se change que par l'action dédiée "Ma zone".
    public function zone(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    // 2FA activée ET confirmée par un premier code
    public function aDoubleAuthentification(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }
}