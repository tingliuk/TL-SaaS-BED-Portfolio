<?php

namespace App\Providers;

use App\Models\Vote;
use App\Policies\VotePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Vote::class => VotePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register custom gates
        Gate::define('voteOnJoke', [VotePolicy::class, 'voteOnJoke']);
        Gate::define('removeVoteFromJoke', [VotePolicy::class, 'removeVoteFromJoke']);
        Gate::define('clearUserVotes', [VotePolicy::class, 'clearUserVotes']);
        Gate::define('clearAllVotes', [VotePolicy::class, 'clearAllVotes']);
    }
}
