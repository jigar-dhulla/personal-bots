<?php

declare(strict_types=1);

namespace App\Bots\Yaarpool;

use App\Bots\Bot;
use App\Bots\Yaarpool\Console\GroupSettingsCommand;
use App\Bots\Yaarpool\Models\GroupSetting;
use App\Bots\Yaarpool\Models\Ride;
use App\Bots\Yaarpool\Models\UserSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class YaarpoolBot implements Bot
{
    public function key(): string
    {
        return 'yaarpool';
    }

    public function name(): string
    {
        return 'Yaarpool';
    }

    public function tagline(): string
    {
        return 'Carpool with your group, right inside WhatsApp. Post a lift or ask for one in plain language.';
    }

    public function agent(): string
    {
        return YaarpoolAgent::class;
    }

    /**
     * @return array<int, class-string<Command>>
     */
    public function commands(): array
    {
        return [GroupSettingsCommand::class];
    }

    /**
     * @return array<int, array{label: string, route: string, pattern: string}>
     */
    public function adminLinks(): array
    {
        return [
            ['label' => 'Rides', 'route' => 'yaarpool.rides.index', 'pattern' => 'yaarpool.rides.*'],
            ['label' => 'Group settings', 'route' => 'yaarpool.group-settings.index', 'pattern' => 'yaarpool.group-settings.*'],
            ['label' => 'User settings', 'route' => 'yaarpool.user-settings.index', 'pattern' => 'yaarpool.user-settings.*'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: int, route: string|null, note: string}>
     */
    public function adminCards(): array
    {
        $upcomingRides = Ride::query()->where('departs_at', '>=', Carbon::now())->count();
        $groupSettings = GroupSetting::query()->count();
        $userSettings = UserSetting::query()->count();

        return [
            [
                'label' => 'Upcoming rides',
                'value' => $upcomingRides,
                'route' => 'yaarpool.rides.index',
                'note' => $upcomingRides === 0
                    ? 'Nothing posted for the future yet.'
                    : Str::ucfirst(Str::plural('ride', $upcomingRides)).' still to depart.',
            ],
            [
                'label' => 'Group settings',
                'value' => $groupSettings,
                'route' => 'yaarpool.group-settings.index',
                'note' => $groupSettings === 0
                    ? 'No chats have default locations yet — add one from the group settings page.'
                    : Str::ucfirst(Str::plural('chat', $groupSettings)).' with default ride locations configured.',
            ],
            [
                'label' => 'User settings',
                'value' => $userSettings,
                'route' => 'yaarpool.user-settings.index',
                'note' => $userSettings === 0
                    ? 'No users have personal defaults yet — they can save them over WhatsApp.'
                    : Str::ucfirst(Str::plural('user', $userSettings)).' with personal ride defaults configured.',
            ],
        ];
    }
}
