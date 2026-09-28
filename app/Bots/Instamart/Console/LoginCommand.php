<?php

declare(strict_types=1);

namespace App\Bots\Instamart\Console;

use App\Bots\Instamart\Models\Connection;
use App\Bots\Instamart\Swiggy\SwiggyAuth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JigarDhulla\SwiggyMcp\Exceptions\OAuthException;

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

        $redirectUri = SwiggyAuth::redirectUri();

        if (SwiggyAuth::usesWebCallback()) {
            $this->warn('SWIGGY_REDIRECT_URI is an HTTPS callback, so log in from the dashboard instead: '.route('instamart.login'));

            return self::FAILURE;
        }

        try {
            $clientId = $auth->clientIdFor($redirectUri);
        } catch (OAuthException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $login = $auth->authorize($clientId, $redirectUri);

        $this->line('1. Open this link and log in with your phone number and OTP:');
        $this->newLine();
        $this->line($login->url);
        $this->newLine();
        $this->line('2. Your browser then goes to '.$redirectUri.', which may not load. That is expected.');
        $this->line('   Copy the full address from the browser bar and paste it below. The code is only valid for about two minutes.');

        $redirected = text(label: 'Redirected URL', required: true);

        try {
            $token = $auth->exchange($login, $redirected);
        } catch (OAuthException $exception) {
            $this->error($exception->getMessage().' Run the command again.');

            return self::FAILURE;
        }

        $connection = Connection::store($clientId, $redirectUri, $token->value, $token->expiresIn);

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
