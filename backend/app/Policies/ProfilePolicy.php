<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Profile;

class ProfilePolicy
{
    public function update(User $user, Profile $profile)
    {
        return $user->id === $profile->user_id;
    }
    public function __construct()
    {
        //
    }
}
