<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait PerteneceAlUsuario
{
    protected static function bootPerteneceAlUsuario(): void
    {
        static::addGlobalScope('usuario', function (Builder $query) {
            if (Auth::check()) {
                $query->where($query->getModel()->getTable().'.usuario_id', Auth::id());
            }
        });

        static::creating(function ($model) {
            if (Auth::check() && empty($model->usuario_id)) {
                $model->usuario_id = Auth::id();
            }
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
