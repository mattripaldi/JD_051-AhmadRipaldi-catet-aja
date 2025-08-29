<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    /**
     * Check if the user is an OAuth user
     */
    public function isOAuthUser(): bool
    {
        return !is_null($this->provider);
    }

    /**
     * Find or create a user from OAuth provider data
     */
    public static function findOrCreateFromProvider($provider, $providerUser)
    {
        $user = static::where('provider', $provider)
            ->where('provider_id', $providerUser->getId())
            ->first();

        if ($user) {
            return $user;
        }

        // Check if user exists with same email
        $existingUser = static::where('email', $providerUser->getEmail())->first();

        if ($existingUser) {
            // Link the OAuth account to existing user
            $existingUser->update([
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
                'avatar' => $providerUser->getAvatar(),
            ]);
            return $existingUser;
        }

        // Create new user
        return static::create([
            'name' => $providerUser->getName(),
            'email' => $providerUser->getEmail(),
            'provider' => $provider,
            'provider_id' => $providerUser->getId(),
            'avatar' => $providerUser->getAvatar(),
            'email_verified_at' => now(), // OAuth emails are pre-verified
        ]);
    }

    /**
     * Send password reset notification with magic link support
     */
    public function sendPasswordResetNotification($token, $magicLink = false)
    {
        if ($magicLink) {
            $this->notify(new \App\Notifications\MagicLinkNotification($token));
        } else {
            $this->notify(new \Illuminate\Auth\Notifications\ResetPassword($token));
        }
    }
}
