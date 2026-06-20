<?php

namespace App\Providers;

use App\Models\InvitationCode;
use App\Policies\InvitationCodePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(InvitationCode::class, InvitationCodePolicy::class);
    }
}
