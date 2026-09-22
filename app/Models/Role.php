<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['name', 'slug'])]

class Role extends Model
{
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
