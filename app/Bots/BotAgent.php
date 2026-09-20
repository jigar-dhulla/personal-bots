<?php

declare(strict_types=1);

namespace App\Bots;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use JigarDhulla\LaravelWhatsApp\Traits\RemembersWhatsAppConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Base class for every bot agent on this WhatsApp number.
 *
 * It owns the parts each bot would otherwise repeat: binding the conversation
 * to a chat, registering the sender as a user, and assembling instructions
 * from a shared skeleton (current date/time, configured triggers, WhatsApp
 * etiquette). A subclass supplies its own {@see persona()}, {@see guidance()},
 * optional extra {@see rules()}, and its tools.
 */
abstract class BotAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersWhatsAppConversations {
        forChat as rememberForChat;
    }

    /**
     * Who this bot is, in a sentence or two. Opens the system prompt.
     */
    abstract protected function persona(): string;

    /**
     * What this bot's tools do and when to call them. Sits between the shared
     * context block and the shared rules.
     */
    abstract protected function guidance(): string;

    /**
     * Bot-specific rules appended to the shared ones. One rule per entry, each
     * rendered as a "- " bullet.
     *
     * @return array<int, string>
     */
    protected function rules(): array
    {
        return [];
    }

    /**
     * Bind the conversation to a chat and, when a sender is known, record them
     * as a user so everyone who interacts with a bot has an account. Runs on
     * every triggering message.
     */
    public function forChat(string $chatJid, ?string $senderJid = null, ?string $senderName = null): static
    {
        $this->rememberForChat($chatJid, $senderJid, $senderName);

        if ($senderJid !== null) {
            User::registerFromWhatsApp($senderJid, $senderName);
        }

        return $this;
    }

    /**
     * The trigger phrases wired to this agent in `config/whatsapp-agent.php`.
     *
     * @return array<int, string>
     */
    protected function triggers(): array
    {
        $entry = (new Collection(config('whatsapp-agent.agents')))
            ->firstWhere(static fn (array $agent) => Arr::get($agent, 'agent') === static::class, []);

        return array_values(array_filter((array) ($entry['triggers'] ?? [])));
    }

    public function instructions(): Stringable|string
    {
        return implode("\n\n", array_filter([
            trim($this->persona()),
            $this->context(),
            trim($this->guidance()),
            $this->ruleBlock(),
        ]));
    }

    /**
     * Shared preamble: when "now" is, and how this agent gets summoned.
     */
    protected function context(): string
    {
        $now = Carbon::now();
        $triggers = $this->triggers();

        $lines = [sprintf(
            'Current date and time: %s (%s). Use this to resolve relative phrases like "tomorrow", "tonight", "Friday", "next Monday".',
            $now->format('l, j F Y H:i'),
            $now->timezoneName,
        )];

        if ($triggers !== []) {
            $lines[] = 'You as agent are mentioned/triggered by these triggers: '.implode(', ', $triggers);
        }

        return implode("\n", $lines);
    }

    /**
     * The shared rules every bot on this number follows, plus the bot's own.
     */
    protected function ruleBlock(): string
    {
        $rules = [...$this->sharedRules(), ...$this->rules()];

        return "Rules:\n".implode("\n", array_map(
            static fn (string $rule): string => '- '.$rule,
            $rules,
        ));
    }

    /**
     * @return array<int, string>
     */
    protected function sharedRules(): array
    {
        return [
            'Do not invent details. If the message is missing something a tool requires, ask one short clarifying question instead of guessing.',
            'Keep replies concise and WhatsApp-friendly: no markdown headings, short sentences, emoji only when natural.',
            'If the message is not something you handle (small talk, greetings, unrelated chatter), reply briefly without calling any tool.',
            'After a tool runs, summarise the outcome to the user in one or two sentences.',
            'Respond ONLY to people who have mentioned/triggered you.',
            'Act ONLY on the most recent message that triggered you. Older chat history is context only — never act on or reply to a past message (e.g. one from a previous day or an earlier sender) just because it appears in the history. If the latest triggering message has no actionable intent, reply briefly and do not call a tool.',
        ];
    }
}
