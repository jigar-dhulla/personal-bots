<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Models;

use Database\Factories\Instamart\ConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The owner's Swiggy login: the OAuth client registered for this app and the
 * access token it was issued. There is only ever one row — the bot orders on
 * a single account. Swiggy issues no refresh tokens, so an expired row means
 * the owner has to run `instamart:login` again.
 *
 * @property string $client_id
 * @property string $redirect_uri
 * @property string $access_token
 * @property Carbon $expires_at
 */
#[UseFactory(ConnectionFactory::class)]
class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    protected $table = 'instamart_connections';

    protected $fillable = [
        'client_id',
        'redirect_uri',
        'access_token',
        'expires_at',
    ];

    protected $hidden = [
        'access_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Replace the saved login with a freshly issued token. There is only ever
     * one login, so any earlier row goes.
     */
    public static function store(string $clientId, string $redirectUri, string $accessToken, int $expiresInSeconds): self
    {
        return DB::transaction(function () use ($clientId, $redirectUri, $accessToken, $expiresInSeconds): self {
            static::query()->delete();

            return static::query()->create([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'access_token' => $accessToken,
                'expires_at' => Carbon::now()->addSeconds($expiresInSeconds),
            ]);
        });
    }

    /**
     * The saved login, whether or not it is still valid.
     */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * The saved login, only while its token is still usable. Swiggy advises
     * treating a token as expired a minute early.
     */
    public static function active(): ?self
    {
        $connection = static::current();

        return $connection !== null && ! $connection->isExpired() ? $connection : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lte(Carbon::now()->addMinute());
    }

    /**
     * Mark the token unusable after Swiggy rejected it, keeping the client id
     * so the next login can reuse the registration.
     */
    public function expire(): void
    {
        $this->forceFill(['expires_at' => Carbon::now()])->save();
    }
}
