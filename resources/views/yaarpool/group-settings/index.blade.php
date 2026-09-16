<x-admin.layout title="Group settings">
    <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <h1 class="font-display text-3xl font-extrabold tracking-tight">Group settings</h1>
        <p class="text-sm opacity-70">{{ $settings->count() }} {{ Str::plural('chat', $settings->count()) }} with default locations</p>
    </header>

    @if ($errors->updateSetting->isNotEmpty())
        <div class="mt-6 rounded-2xl border border-rose-600/20 bg-rose-100 px-5 py-3 text-sm font-medium text-rose-800">
            {{ $errors->updateSetting->first() }}
        </div>
    @endif

    <div class="mt-8 rounded-3xl border border-ink/10 bg-white p-6 shadow-card">
        <h2 class="font-display text-lg font-bold">Add a chat</h2>
        <p class="mt-1 text-sm opacity-70">
            Rides posted in this chat will fall back to these locations when the message doesn't mention them.
            @if ($knownGroups->isEmpty())
                No groups found in the wacli store — enter the JID manually, or discover it with <code class="rounded bg-ink/5 px-1.5 py-0.5 font-mono text-xs">php artisan wa:groups</code>.
            @else
                Groups are read from the local wacli store.
            @endif
        </p>

        <form method="POST" action="{{ route('yaarpool.group-settings.store') }}" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-start">
            @csrf

            <div>
                <label for="chat_jid" class="block text-sm font-bold">{{ $knownGroups->isEmpty() ? 'Chat JID' : 'Group' }}</label>
                @if ($knownGroups->isEmpty())
                    <input id="chat_jid" type="text" name="chat_jid" value="{{ old('chat_jid') }}" required placeholder="1203…@g.us"
                        class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 font-mono text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @else
                    <select id="chat_jid" name="chat_jid" required
                        class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="" disabled @selected(old('chat_jid') === null)>Choose a group…</option>
                        @foreach ($knownGroups as $group)
                            <option value="{{ $group['jid'] }}" @selected(old('chat_jid') === $group['jid']) @disabled($group['configured'])>
                                {{ $group['name'] !== '' ? $group['name'].' — '.$group['jid'] : $group['jid'] }}{{ $group['configured'] ? ' (already configured)' : '' }}
                            </option>
                        @endforeach
                    </select>
                @endif
                @error('chat_jid')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="default_from_location" class="block text-sm font-bold">Default origin</label>
                <input id="default_from_location" type="text" name="default_from_location" value="{{ old('default_from_location') }}" required placeholder="e.g. Copenhagen"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('default_from_location')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="default_to_location" class="block text-sm font-bold">Default destination <span class="font-medium opacity-60">(optional)</span></label>
                <input id="default_to_location" type="text" name="default_to_location" value="{{ old('default_to_location') }}" placeholder="e.g. Aarhus"
                    class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('default_to_location')
                    <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white shadow-card transition hover:-translate-y-0.5 hover:bg-emerald-600 hover:shadow-card-lg">
                    Save defaults
                </button>
            </div>
        </form>
    </div>

    @if ($settings->isEmpty())
        <div class="mt-8 rounded-3xl border border-ink/10 bg-white p-12 text-center shadow-card">
            <p class="font-display text-xl font-bold">No group defaults yet</p>
            <p class="mt-2 text-sm opacity-70">Add a chat above and rides posted there can omit their origin or destination.</p>
        </div>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($settings as $setting)
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <h2 class="min-w-0 font-mono text-lg font-bold break-words">{{ $setting->chat_jid }}</h2>
                        <form method="POST" action="{{ route('yaarpool.group-settings.destroy', $setting) }}" class="shrink-0" onsubmit="return confirm('Clear the defaults for {{ $setting->chat_jid }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-white px-5 py-2.5 text-sm font-bold text-rose-600 shadow-card transition hover:-translate-y-0.5 hover:bg-rose-50 hover:shadow-card-lg">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                Clear
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('yaarpool.group-settings.update', $setting) }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="from-{{ $setting->id }}" class="block text-sm font-bold">Default origin</label>
                            <input id="from-{{ $setting->id }}" type="text" name="default_from_location" value="{{ $setting->default_from_location }}" required
                                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label for="to-{{ $setting->id }}" class="block text-sm font-bold">Default destination <span class="font-medium opacity-60">(optional)</span></label>
                            <input id="to-{{ $setting->id }}" type="text" name="default_to_location" value="{{ $setting->default_to_location }}"
                                class="mt-1.5 w-full rounded-xl border border-ink/15 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        <div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full border border-ink/10 bg-white px-5 py-2.5 text-sm font-bold shadow-card transition hover:-translate-y-0.5 hover:shadow-card-lg">
                                Save changes
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.layout>
