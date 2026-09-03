<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ModeloUsuarioPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Model $modelo): bool { return $modelo->usuario_id === $user->id; }
    public function create(User $user): bool { return true; }
    public function update(User $user, Model $modelo): bool { return $modelo->usuario_id === $user->id; }
    public function delete(User $user, Model $modelo): bool { return $modelo->usuario_id === $user->id; }
}
