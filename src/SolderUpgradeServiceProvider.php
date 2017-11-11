<?php

/*
 * This file is part of Solder.
 *
 * (c) Kyle Klaus <kklaus@indemnity83.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SolderIO\Upgrade;

use Illuminate\Support\ServiceProvider;
use SolderIO\Upgrade\Console\UpgradeCommand;

class SolderUpgradeServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('upgrade'),
        ], 'migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpgradeCommand::class,
            ]);
        }
    }
}
