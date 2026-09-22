<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Console;

use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function Laravel\Prompts\text;

#[Signature('instamart:login {--status : Only show whether the saved login is still valid}')]
#[Description('Log the Instamart bot into your Swiggy account (OAuth + PKCE; phone and OTP in your browser).')]
class LoginCommand extends Command
{
    public function handle(SwiggyAuth $auth): int
    {
        $current = Connection::current();

        if ($this->option('status')) {
            return $this->status($current);
        }

        $redirectUri = (string) config('services.swiggy.redirect_uri');

        try {
            $clientId = $current->client_id ?? $auth->register($redirectUri);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $pkce = SwiggyAuth::pkcePair();
        $state = SwiggyAuth::state();

        $this->line('1. Open this link and log in with your phone number and OTP:');
        $this->newLine();
        $this->line($auth->authorizationUrl($clientId, $redirectUri, $pkce['challenge'], $state));
        $this->newLine();
        $this->line('2. Your browser then goes to '.$redirectUri.', which may not load. That is expected.');
        $this->line('   Copy the full address from the browser bar and paste it below. The code is only valid for about two minutes.');

        $redirected = text(label: 'Redirected URL', required: true);

        parse_str((string) parse_url(trim($redirected), PHP_URL_QUERY), $query);

        if (($query['state'] ?? null) !== $state) {
            $this->error('That URL does not belong to this login attempt (state mismatch). Run the command again.');

            return self::FAILURE;
        }

        if (blank($query['code'] ?? null)) {
            $this->error('No authorization code in that URL'.(filled($query['error'] ?? null) ? ': '.$query['error'] : '.'));

            return self::FAILURE;
        }

        try {
            $token = $auth->exchange($clientId, (string) $query['code'], $pkce['verifier'], $redirectUri);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $connection = DB::transaction(function () use ($clientId, $token): Connection {
            Connection::query()->delete();

            return Connection::query()->create([
                'client_id' => $clientId,
                'access_token' => $token['access_token'],
                'expires_at' => Carbon::now()->addSeconds($token['expires_in']),
            ]);
        });

        $this->info(sprintf('Logged in to Swiggy. The token expires %s (%s).', $connection->expires_at->toDayDateTimeString(), $connection->expires_at->diffForHumans()));

        return self::SUCCESS;
    }

    private function status(?Connection $connection): int
    {
        if ($connection === null) {
            $this->warn('Not logged in. Run instamart:login.');

            return self::FAILURE;
        }

        if ($connection->isExpired()) {
            $this->warn('The Swiggy login has expired. Run instamart:login again.');

            return self::FAILURE;
        }

        $this->info(sprintf('Logged in. The token expires %s (%s).', $connection->expires_at->toDayDateTimeString(), $connection->expires_at->diffForHumans()));

        return self::SUCCESS;
    }
}
