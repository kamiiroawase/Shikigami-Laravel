<?php

/** @noinspection PhpUnused */

namespace App\Providers;

use Illuminate\Support\Collection;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider as BaseTelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * @noinspection PhpMissingParentCallCommonInspection
     * @noinspection PhpUnused
     */
    public function register(): void
    {
        $this->app->register(BaseTelescopeServiceProvider::class);

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);

        Telescope::filterBatch(function (Collection $entries) {
            return $entries->contains(function (IncomingEntry $entry) {
                return $entry->isReportableException() ||
                    $entry->isException();
            });
        });
    }
}
