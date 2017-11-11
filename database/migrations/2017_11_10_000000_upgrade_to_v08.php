<?php

/*
 * This file is part of Solder.
 *
 * (c) Kyle Klaus <kklaus@indemnity83.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpgradeToV08 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->migrateUsers();
        $this->migrateKeys();
        $this->migrateClients();
        $this->migrateReleases();
        $this->migratePackages();
        $this->migrateBuilds();
        $this->migrateModpacks();
        $this->migrateBundles();
        $this->migrateRoles();
        $this->migratePermissions();

        $this->locateReleaseFiles();
        $this->locateRecommendedBuilds();
        $this->locateLatestBuilds();
        $this->locateModpackIcon();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     * @throws Exception
     */
    public function down()
    {
        throw new Exception('Please restore your backup to roll back the v0.8 upgrade.');
    }

    private function migrateUsers()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('created_ip');
            $table->dropColumn('last_ip');
            $table->dropColumn('updated_by_ip');
            $table->dropColumn('created_by_user_id');
            $table->dropColumn('updated_by_user_id');
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function migrateKeys()
    {
        Schema::table('keys', function (Blueprint $table) {
            $table->renameColumn('api_key', 'token');
        });
    }

    private function migrateClients()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('name', 'title');
            $table->renameColumn('uuid', 'token');
        });

        Schema::table('client_modpack', function (Blueprint $table) {
            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');
        });
    }

    private function migrateReleases()
    {
        Schema::rename('modversions', 'releases');

        Schema::table('releases', function (Blueprint $table) {
            $table->renameColumn('mod_id', 'package_id');
            $table->string('path');
        });
    }

    private function migratePackages()
    {
        Schema::rename('mods', 'packages');

        Schema::table('packages', function (Blueprint $table) {
            $table->renameColumn('name', 'slug');
            $table->renameColumn('pretty_name', 'name');
            $table->renameColumn('link', 'website_url');
            $table->renameColumn('donatelink', 'donation_url');
        });
    }

    private function locateReleaseFiles()
    {
        DB::table('releases')
            ->join('packages', 'releases.package_id', 'packages.id')
            ->update(['path' => DB::raw("concat('mods/', packages.slug, '/', packages.slug, '-', releases.version, '.zip')")]);
    }

    private function migrateBuilds()
    {
        Schema::table('builds', function (Blueprint $table) {
            $table->renameColumn('minecraft', 'minecraft_version');
            $table->renameColumn('min_java', 'java_version');
            $table->renameColumn('min_memory', 'required_memory');
            $table->renameColumn('forge', 'forge_version');
            $table->string('status');
        });

        DB::table('builds')
            ->where('is_published', 0)
            ->where('private', 0)
            ->update(['status' => 'draft']);

        DB::table('builds')
            ->where('is_published', 1)
            ->where('private', 0)
            ->update(['status' => 'public']);

        DB::table('builds')
            ->where('private', 1)
            ->update(['status' => 'private']);

        Schema::table('builds', function (Blueprint $table) {
            $table->dropColumn('is_published');
            $table->dropColumn('private');
        });
    }

    private function migrateModpacks()
    {
        Schema::table('modpacks', function (Blueprint $table) {
            $table->unsignedInteger('recommended_build_id')->nullable();
            $table->unsignedInteger('latest_build_id')->nullable();
            $table->string('status')->default('public');
            $table->string('icon_path')->nullable();
            $table->dropColumn('icon_md5');
            $table->dropColumn('logo');
            $table->dropColumn('logo_url');
            $table->dropColumn('logo_md5');
            $table->dropColumn('background');
            $table->dropColumn('background_url');
            $table->dropColumn('background_md5');
            $table->dropColumn('order');
            $table->dropColumn('url');
        });

        DB::table('modpacks')
            ->where('hidden', 1)
            ->orWhere('private', 1)
            ->update(['status' => 'private']);

        Schema::table('modpacks', function (Blueprint $table) {
            $table->dropColumn('hidden');
            $table->dropColumn('private');
        });
    }

    private function locateRecommendedBuilds()
    {
        DB::table('modpacks')
            ->join('builds', function ($join) {
                $join->on('builds.version', '=', 'modpacks.recommended')
                    ->where('builds.modpack_id', DB::raw('modpacks.id'));
            })
            ->update(['recommended_build_id' => DB::raw('builds.id')]);

        Schema::table('modpacks', function (Blueprint $table) {
            $table->dropColumn('recommended');
        });
    }

    private function locateLatestBuilds()
    {
        DB::table('modpacks')
            ->join('builds', function ($join) {
                $join->on('builds.version', '=', 'modpacks.latest')
                    ->where('builds.modpack_id', DB::raw('modpacks.id'));
            })
            ->update(['latest_build_id' => DB::raw('builds.id')]);

        Schema::table('modpacks', function (Blueprint $table) {
            $table->dropColumn('latest');
        });
    }

    private function locateModpackIcon()
    {
        DB::table('modpacks')->get()->each(function ($modpack) {
            if ($modpack->icon == 0) {
                return;
            }

            $defaultPath = "resources/{$modpack->slug}/icon.png";
            if (Storage::exists($defaultPath)) {
                DB::table('modpacks')->where('id',
                    $modpack->id)->update(['icon_path' => $defaultPath]);

                return;
            }

            try {
                Storage::put($defaultPath, fopen($modpack->icon_url, 'r'));
                DB::table('modpacks')->where('id',
                    $modpack->id)->update(['icon_path' => $defaultPath]);

                return;
            } catch (Exception $e) {
                return;
            }
        });

        Schema::table('modpacks', function (Blueprint $table) {
            $table->dropColumn('icon');
            $table->dropColumn('icon_url');
        });
    }

    private function migrateBundles()
    {
        Schema::rename('build_modversion', 'build_release');

        Schema::table('build_release', function (Blueprint $table) {
            $table->renameColumn('modversion_id', 'release_id');
        });
    }

    private function migrateRoles()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tag');
        });

        // Create the application roles
        $roles = collect([
            ['tag' => 'manage-keys'],
            ['tag' => 'manage-users'],
            ['tag' => 'manage-clients'],
            ['tag' => 'create-modpack'],
            ['tag' => 'update-modpack'],
            ['tag' => 'delete-modpack'],
            ['tag' => 'create-package'],
            ['tag' => 'update-package'],
            ['tag' => 'delete-package'],
        ]);

        $roles->each(function ($role) {
            DB::table('roles')->insert($role);
        });
    }

    private function migratePermissions()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });

        Schema::create('collaborators', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('modpack_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });

        $roles = DB::table('roles')->get();

        DB::table('user_permissions')->get()->each(function ($permission) use ($roles) {
            if ($permission->solder_full) {
                DB::table('users')->where('id', $permission->user_id)->update(['is_admin' => true]);
            }

            if ($permission->solder_users) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'manage-users')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->mods_create) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'create-package')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->mods_manage) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'update-package')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->mods_delete) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'delete-package')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->solder_keys) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'manage-keys')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->solder_clients) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'manage-clients')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->modpacks_create) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'create-modpack')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->modpacks_manage) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'update-modpack')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->modpacks_delete) {
                DB::table('permissions')->insert([
                    'user_id' => $permission->user_id,
                    'role_id' => $roles->where('tag', 'delete-modpack')->first()->id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                ]);
            }

            if ($permission->modpacks !== null) {
                $modpacks = explode(',', $permission->modpacks);
                foreach ($modpacks as $modpack) {
                    DB::table('collaborators')->insert([
                        'modpack_id' => $modpack,
                        'user_id' => $permission->user_id,
                        'created_at' => $permission->created_at,
                        'updated_at' => $permission->updated_at,
                    ]);
                }
            }
        });

        Schema::dropIfExists('user_permissions');
    }
}
