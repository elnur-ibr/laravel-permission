<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePermissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('modules')){
            Schema::create('modules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('companies')){
            Schema::create('companies', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
            });
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid');
            $table->string('name');
            $table->string('display_name');
            $table->string('guard_name');
            $table->unsignedBigInteger('module_id');
            $table->string('controller');
            $table->string('method');
            $table->timestamps();

            $table->foreign('module_id')
                ->references('id')
                ->on('modules')
                ->onDelete('cascade');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid');
            $table->string('name');
            $table->string('display_name');
            $table->string('guard_name');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();

            $table->foreign('module_id')
                ->references('id')
                ->on('modules')
                ->onDelete('cascade');
        });

        Schema::create('company_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->index(['company_id', 'permission_id']);
            $table->unique(['company_id', 'permission_id']);

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });

        Schema::create('user_has_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_permission_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'company_permission_id']);
            $table->unique(['user_id', 'company_permission_id']);

            $table->foreign('company_permission_id')
                ->references('id')
                ->on('company_has_permissions')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::create('company_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->index(['company_id', 'role_id']);
            $table->unique(['role_id','company_id']);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });

        Schema::create('user_has_roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_role_id');
            $table->timestamps();

            $table->index(['user_id', 'company_role_id']);
            $table->unique(['user_id', 'company_role_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('company_role_id')
                ->references('id')
                ->on('company_has_roles')
                ->onDelete('cascade');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });

        Schema::create('company_role_has_company_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_permission_id');
            $table->unsignedBigInteger('company_role_id');
            $table->timestamps();

            $table->foreign('company_permission_id')
                ->references('id')
                ->on('company_permissions')
                ->onDelete('cascade');

            $table->foreign('company_role_id')
                ->references('id')
                ->on('company_roles')
                ->onDelete('cascade');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('user_has_roles');
        Schema::drop('user_has_permissions');
        Schema::drop('company_role_has_company_permissions');

        Schema::drop('company_roles');
        Schema::drop('company_permissions');

        Schema::drop('role_has_permissions');
        Schema::drop('roles');
        Schema::drop('permissions');
    }
}
