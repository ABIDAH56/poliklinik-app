<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
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
        // Blokir db:wipe dan migrate:fresh/refresh/reset/rollback kecuali pada database khusus pengujian
        // (nama berakhiran "_test"). Database kerja tidak bisa terhapus tanpa sengaja oleh perintah itu.
        $database = (string) config('database.connections.' . config('database.default') . '.database');
        DB::prohibitDestructiveCommands(! str_ends_with($database, '_test'));
    }
}
