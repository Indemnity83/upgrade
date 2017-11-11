<?php

/*
 * This file is part of Solder.
 *
 * (c) Kyle Klaus <kklaus@indemnity83.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SolderIO\Upgrade\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpgradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'solder:upgrade';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the commands necessary to upgrade Solder from v0.7.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $legacyConfig = array_dot(include(app_path('config/database.php')));

        if ($legacyConfig['default'] != 'mysql') {
            $this->error('The upgrade only supports existing MySQL databases.');
            exit;
        }

        $host = $this->ask('Database Host', $legacyConfig['connections.mysql.host']);
        $database = $this->ask('Database', $legacyConfig['connections.mysql.database']);
        $username = $this->ask('Username', $legacyConfig['connections.mysql.username']);
        $password = $this->ask('Password', $legacyConfig['connections.mysql.password']);

        config(['database.connections.mysql.host' => $host]);
        config(['database.connections.mysql.database' => $database]);
        config(['database.connections.mysql.username' => $username]);
        config(['database.connections.mysql.password' => $password]);

        $this->changeEnv([
            'DB_HOST' => $host,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ]);

        $this->call('vendor:publish', ['--provider' => 'SolderIO\Upgrade\SolderUpgradeServiceProvider']);
        $this->call('migrate', ['--path' => 'database/upgrade']);

        DB::table('migrations')->truncate();
        DB::table('migrations')->insert([
            ['migration' => '2014_10_12_000000_create_users_table', 'batch' => 1],
            ['migration' => '2014_10_12_100000_create_password_resets_table', 'batch' => 1],
            ['migration' => '2017_09_04_014947_create_modpacks_table', 'batch' => 1],
            ['migration' => '2017_09_04_040655_create_builds_table', 'batch' => 1],
            ['migration' => '2017_09_05_054554_create_releases_table', 'batch' => 1],
            ['migration' => '2017_09_05_054822_create_build_release_table', 'batch' => 1],
            ['migration' => '2017_09_06_032546_create_packages_table', 'batch' => 1],
            ['migration' => '2017_09_06_154609_create_keys_table', 'batch' => 1],
            ['migration' => '2017_09_09_154654_create_clients_table', 'batch' => 1],
            ['migration' => '2017_09_09_155207_create_client_modpack_table', 'batch' => 1],
            ['migration' => '2017_11_08_182018_create_collaborators_table', 'batch' => 1],
            ['migration' => '2017_10_14_172600_create_roles_table', 'batch' => 1],
            ['migration' => '2017_10_14_172837_create_permissions_table', 'batch' => 1],
        ]);

        // Normal setup
        $this->call('key:generate');
        $this->call('migrate');
        $this->call('passport:install', ['--force']);
    }

    /**
     * Write configuration values to the .env file.
     *
     * based on http://laravel-tricks.com/tricks/change-the-env-dynamically
     *
     * @param array $data
     * @return bool
     */
    protected function changeEnv($data = [])
    {
        // Read .env-file
        $env = $this->readEnv();

        // Split string on every " " and write into array
        $env = preg_split('/\s+/', $env);

        // Loop through given data
        foreach ((array) $data as $key => $value) {

            // Loop through .env-data
            foreach ($env as $env_key => $env_value) {

                // Turn the value into an array and stop after the first split
                // So it's not possible to split e.g. the App-Key by accident
                $entry = explode('=', $env_value, 2);

                // Check, if new key fits the actual .env-key
                if ($entry[0] == $key) {
                    // If yes, overwrite it with the new one
                    $env[$env_key] = $key.'='.$value;
                } else {
                    // If not, keep the old one
                    $env[$env_key] = $env_value;
                }
            }
        }

        // Turn the array back to an String
        $env = implode("\n", $env);

        // And overwrite the .env with the new data
        file_put_contents(base_path().'/.env', $env);
    }

    /**
     * @return string
     */
    protected function readEnv()
    {
        return file_get_contents(base_path().'/.env');
    }
}
