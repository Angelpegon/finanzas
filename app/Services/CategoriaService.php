<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CuentaContable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoriaService
{
    public function crear(int $usuarioId, string $nombre, string $tipo): Categoria
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }
        if (! in_array($tipo, ['gasto', 'ingreso'], true)) {
            throw new \InvalidArgumentException('El tipo de categoría debe ser gasto o ingreso.');
        }

        return DB::transaction(function () use ($usuarioId, $nombre, $tipo): Categoria {
            $this->assertNombreLibre($usuarioId, $tipo, $nombre);

            $codigo = $tipo === 'ingreso' ? '4100' : '5100';
            $cuentaId = CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('codigo', $codigo)
                ->value('id');
            if (! $cuentaId) {
                throw new \InvalidArgumentException('No hay cuenta contable de catálogo para esa categoría.');
            }

            return Categoria::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'cuenta_contable_id' => $cuentaId,
                'nombre' => $nombre,
                'tipo' => $tipo,
            ]);
        });
    }

    public function actualizar(int $usuarioId, int $categoriaId, string $nombre): Categoria
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre de la categoría es obligatorio.');
        }

        return DB::transaction(function () use ($usuarioId, $categoriaId, $nombre): Categoria {
            $categoria = Categoria::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($categoriaId);

            $this->assertNombreLibre($usuarioId, (string) $categoria->tipo, $nombre, (int) $categoria->id);
            $categoria->forceFill(['nombre' => $nombre])->save();

            return $categoria->fresh();
        });
    }

    private function assertNombreLibre(int $usuarioId, string $tipo, string $nombre, ?int $exceptoId = null): void
    {
        $existe = Categoria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', $tipo)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($exceptoId !== null, fn ($q) => $q->where('id', '<>', $exceptoId))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya tienes una categoría '.$tipo.' con ese nombre.',
            ]);
        }
    }
}
