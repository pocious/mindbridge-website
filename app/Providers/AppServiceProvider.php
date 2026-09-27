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

        // The sign-in pages show the firm's name from its settings.
        View::composer('auth.*', function ($view) {
            $name = 'Virtual Law Firm';
            try {
                if (Schema::hasTable('vlf_settings')) {
                    $name = Setting::where('key', 'firm_name')->first()?->value ?: $name;
                }
            } catch (Throwable) {
                // No database yet — keep the default name.
            }
            $view->with('firmName', $name);
        });
    }
}
