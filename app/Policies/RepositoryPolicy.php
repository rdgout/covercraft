<?php

namespace App\Policies;

use App\Models\Repository;
use App\Models\User;

class RepositoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Repository $repository): bool
    {
        return $user->hasAccessToRepository($repository);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->teams()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Repository $repository): bool
    {
        return $user->hasAccessToRepository($repository);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Repository $repository): bool
    {
        return $user->hasAccessToRepository($repository);
    }

    /**
     * Determine whether the user can upload coverage for the repository.
     */
    public function uploadCoverage(User $user, Repository $repository): bool
    {
        return $user->hasAccessToRepository($repository);
    }
}
