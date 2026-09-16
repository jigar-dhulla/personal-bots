<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Bots\BotAgent;

/**
 * A minimal agent used to exercise {@see BotAgent} without leaning on any real
 * bot's prompt wording.
 */
class EchoAgent extends BotAgent
{
    protected function persona(): string
    {
        return 'You are Echo, a test bot.';
    }

    protected function guidance(): string
    {
        return 'Call `echo_back` when the user says anything at all.';
    }

    /**
     * @return array<int, string>
     */
    protected function rules(): array
    {
        return ['Echo the message back verbatim.'];
    }

    public function tools(): iterable
    {
        return [];
    }
}
