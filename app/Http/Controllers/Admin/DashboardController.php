<?php

namespace App\Http\Controllers\Admin;

use App\Bots\Bot;
use App\Bots\BotRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(BotRegistry $bots): View
    {
        return view('admin.dashboard', [
            'failedJobsCount' => DB::table('failed_jobs')->count(),
            'queuedJobsCount' => DB::table('jobs')->count(),
            'botSections' => $bots->all()
                ->map(fn (Bot $bot): array => [
                    'name' => $bot->name(),
                    'cards' => $bot->adminCards(),
                ])
                ->filter(fn (array $section): bool => $section['cards'] !== [])
                ->values(),
        ]);
    }
}
