<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait ResolvesConstructionActor
{
    protected function constructionActor(): ?Model
    {
        foreach (['superadmin', 'admin', 'member', 'callingteam'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return Auth::user();
    }
}
