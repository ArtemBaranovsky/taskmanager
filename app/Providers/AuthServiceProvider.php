<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\CustomSanctumGuard;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\RequestGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Guard;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::resolved(function ($auth) {
            $auth->extend('token', function ($app, $name, array $config) use ($auth) {
                $customGuard = new RequestGuard(
                    new CustomSanctumGuard($auth, config('sanctum.expiration'), $config['provider']),
                    request(),
                    auth()->createUserProvider($config['provider'] ?? null)
                );

                return $customGuard;
            });
        });

        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
