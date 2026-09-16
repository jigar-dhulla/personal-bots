<?php

namespace App\Bots\Yaarpool\Http\Controllers;

use App\Bots\Yaarpool\Models\GroupSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use Throwable;

class GroupSettingsController extends Controller
{
    public function index(Wacli $wacli): View
    {
        $settings = GroupSetting::query()->orderBy('chat_jid')->get();

        return view('yaarpool.group-settings.index', [
            'settings' => $settings,
            'knownGroups' => $this->knownGroups($wacli, $settings->pluck('chat_jid')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'chat_jid' => ['required', 'string', 'max:255', 'unique:group_settings,chat_jid'],
            'default_from_location' => ['required', 'string', 'max:255'],
            'default_to_location' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = GroupSetting::query()->create($validated);

        return redirect()
            ->route('yaarpool.group-settings.index')
            ->with('status', sprintf('Saved defaults for %s.', $setting->chat_jid));
    }

    public function update(Request $request, GroupSetting $groupSetting): RedirectResponse
    {
        $validated = $request->validateWithBag('updateSetting', [
            'default_from_location' => ['required', 'string', 'max:255'],
            'default_to_location' => ['nullable', 'string', 'max:255'],
        ]);

        $groupSetting->update($validated);

        return redirect()
            ->route('yaarpool.group-settings.index')
            ->with('status', sprintf('Saved defaults for %s.', $groupSetting->chat_jid));
    }

    public function destroy(GroupSetting $groupSetting): RedirectResponse
    {
        $groupSetting->delete();

        return redirect()
            ->route('yaarpool.group-settings.index')
            ->with('status', sprintf('Cleared defaults for %s.', $groupSetting->chat_jid));
    }

    /**
     * Joined groups from the wacli store, or an empty collection when wacli is unavailable.
     *
     * @param  Collection<int, string>  $configuredJids
     * @return Collection<int, array{jid: string, name: string, configured: bool}>
     */
    private function knownGroups(Wacli $wacli, Collection $configuredJids): Collection
    {
        try {
            $groups = $wacli->groups();
        } catch (Throwable) {
            return new Collection;
        }

        return (new Collection($groups))
            ->filter(fn (array $group): bool => filled($group['JID'] ?? null))
            ->map(fn (array $group): array => [
                'jid' => (string) $group['JID'],
                'name' => (string) ($group['Name'] ?? ''),
                'configured' => $configuredJids->contains($group['JID']),
            ])
            ->sortBy(fn (array $group): string => mb_strtolower($group['name']))
            ->values();
    }
}
