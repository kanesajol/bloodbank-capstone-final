<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction(
                'REGEXP_REPLACE',
                static function (?string $subject, string $pattern, string $replacement): ?string {
                    if ($subject === null) {
                        return null;
                    }

                    return preg_replace('/'.$pattern.'/u', $replacement, $subject) ?? $subject;
                },
                3
            );
        }
    }
}
