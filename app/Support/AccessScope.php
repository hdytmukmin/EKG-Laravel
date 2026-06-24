<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessScope
{
    public static function apply(Builder $query, User $user, string $column = 'puskesmas_id'): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where($column, $user->puskesmas_id);
    }
}
