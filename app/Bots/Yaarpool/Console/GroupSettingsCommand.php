<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool\Console;

use App\Bots\Yaarpool\Models\GroupSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

#[Signature('group:settings
    {chat? : The chat JID (use wa:chats / wa:groups to discover it)}
    {--from= : Default origin assumed when a ride omits its pickup}
    {--to= : Default destination assumed when a ride omits its drop-off}
    {--clear : Remove the saved defaults for the chat}')]
#[Description('View or set the default origin/destination assumed for rides posted in a chat.')]
class GroupSettingsCommand extends Command
{
    public function handle(): int
    {
        $chatJid = $this->argument('chat');

        if ($chatJid === null) {
            return $this->listAll();
        }

        if ($this->option('clear')) {
            return $this->clear($chatJid);
        }

        if ($this->option('from') !== null || $this->option('to') !== null) {
            return $this->save($chatJid);
        }

        return $this->show($chatJid);
    }

    private function listAll(): int
    {
        $settings = GroupSetting::query()->orderBy('chat_jid')->get();

        if ($settings->isEmpty()) {
            $this->info('No group defaults configured yet. Set one with: group:settings <chat> --from="..."');

            return self::SUCCESS;
        }

        table(
            ['Chat JID', 'Default origin', 'Default destination'],
            $settings->map(fn (GroupSetting $setting): array => [
                $setting->chat_jid,
                $setting->default_from_location,
                $setting->default_to_location ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function show(string $chatJid): int
    {
        $setting = GroupSetting::forChat($chatJid);

        if ($setting === null) {
            $this->warn(sprintf('No defaults set for %s. Add one with --from / --to.', $chatJid));

            return self::SUCCESS;
        }

        table(
            ['Chat JID', 'Default origin', 'Default destination'],
            [[
                $setting->chat_jid,
                $setting->default_from_location,
                $setting->default_to_location ?? '—',
            ]],
        );

        return self::SUCCESS;
    }

    private function save(string $chatJid): int
    {
        $existing = GroupSetting::forChat($chatJid);

        $from = $this->option('from') ?? $existing?->default_from_location;

        if (blank($from)) {
            $this->error('A default origin is required. Pass --from="..." the first time you configure a chat.');

            return self::FAILURE;
        }

        $to = $this->option('to') ?? $existing?->default_to_location;

        $setting = GroupSetting::query()->updateOrCreate(
            ['chat_jid' => $chatJid],
            [
                'default_from_location' => $from,
                'default_to_location' => blank($to) ? null : $to,
            ],
        );

        $this->info(sprintf(
            'Saved defaults for %s: origin "%s", destination "%s".',
            $setting->chat_jid,
            $setting->default_from_location,
            $setting->default_to_location ?? '—',
        ));

        return self::SUCCESS;
    }

    private function clear(string $chatJid): int
    {
        $deleted = GroupSetting::query()->where('chat_jid', $chatJid)->delete();

        $this->info($deleted > 0
            ? sprintf('Cleared defaults for %s.', $chatJid)
            : sprintf('No defaults were set for %s.', $chatJid));

        return self::SUCCESS;
    }
}
