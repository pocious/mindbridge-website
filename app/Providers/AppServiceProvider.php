<?php

namespace App\Providers;

use App\Models\Vlf\Document;
use App\Models\Vlf\Invoice;
use App\Models\Vlf\Matter;
use App\Models\Vlf\Notification;
use App\Models\Vlf\Setting;
use App\Policies\DocumentPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MatterPolicy;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        Gate::policy(Matter::class, MatterPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);

        // The public homepage and the sign-in pages show the firm's name (and the
        // homepage its address) from its settings.
        View::composer(['welcome', 'auth.*'], function ($view) {
            $name = 'Virtual Law Firm';
            $address = null;
            try {
                if (Schema::hasTable('vlf_settings')) {
                    $settings = Setting::whereIn('key', ['firm_name', 'firm_address'])->pluck('value', 'key');
                    $name = ($settings['firm_name'] ?? '') ?: $name;
                    $address = ($settings['firm_address'] ?? '') ?: null;
                }
            } catch (Throwable) {
                // No database yet — keep the defaults.
            }
            $view->with(['firmName' => $name, 'firmAddress' => $address]);
        });
    }
}
