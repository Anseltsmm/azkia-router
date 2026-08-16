<?php

namespace App\Providers;

use App\Models\DashboardPopup;
use App\Models\InboxMessage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $isAdminHost = request()->getHost() === 'admin.azkia.cloud';

            if (! $user || $isAdminHost) {
                $view->with(['inboxMessages' => collect(), 'unreadInboxCount' => 0, 'dashboardPopup' => null]);

                return;
            }

            $query = InboxMessage::where('user_id', $user->id);
            $view->with([
                'inboxMessages' => (clone $query)->latest()->limit(5)->get(),
                'unreadInboxCount' => (clone $query)->whereNull('read_at')->count(),
                'dashboardPopup' => DashboardPopup::currentlyActive()->latest()->first(),
            ]);
        });
    }
}
