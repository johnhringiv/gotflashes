<?php

namespace App\Policies;

use App\Models\Flash;
use App\Models\User;
use App\Support\SecurityLog;

class FlashPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Flash $flash): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Flash $flash): bool
    {
        return $this->ownsOrLogDenied('update', $user, $flash);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Flash $flash): bool
    {
        return $this->ownsOrLogDenied('delete', $user, $flash);
    }

    /**
     * Ownership check shared by the update/delete abilities: logs a security event
     * on denial (surfaces IDOR/ownership-probing that otherwise only shows as an
     * opaque 403) and returns whether the user owns the flash.
     */
    private function ownsOrLogDenied(string $ability, User $user, Flash $flash): bool
    {
        $owns = $flash->user_id === $user->id;

        if (! $owns) {
            SecurityLog::warning('flash_authorization_denied', 'Flash authorization denied', [
                'ability' => $ability,
                'user_id' => $user->id,
                'flash_id' => $flash->id,
                'flash_owner_id' => $flash->user_id,
            ]);
        }

        return $owns;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Flash $flash): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Flash $flash): bool
    {
        return false;
    }
}
