<?php

namespace App\Http\Controllers;

use App\Services\CalendarioFinancieroService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarioController extends Controller
{
    public function __invoke(Request $request, CalendarioFinancieroService $calendario): View
    {
        $anio = (int) ($request->integer('anio') ?: now()->year);
        $mes = max(1, min(12, (int) ($request->integer('mes') ?: now()->month)));
        $fecha = now()->copy()->setDate($anio, $mes, 1)->startOfMonth();

        $eventos = $calendario->mensual($request->user()->id, $fecha->year, $fecha->month);
        $grilla = $calendario->grillaMensual($fecha, $eventos);
        $resumen = $calendario->resumenMensual($eventos);

        $diaSeleccionado = $this->resolverDiaSeleccionado($request->query('dia'), $fecha, $eventos);
        $eventosDia = collect($eventos)
            ->where('fecha', $diaSeleccionado)
            ->values()
            ->all();

        return view('calendario.index', [
            'fecha' => $fecha,
            'eventos' => $eventos,
            'grilla' => $grilla,
            'resumen' => $resumen,
            'diaSeleccionado' => $diaSeleccionado,
            'eventosDia' => $eventosDia,
            'mesAnterior' => $fecha->copy()->subMonth(),
            'mesSiguiente' => $fecha->copy()->addMonth(),
            'esMesActual' => $fecha->isSameMonth(now()),
        ]);
    }

    /**
     * @param  array<int, array{fecha: string}>  $eventos
     */
    private function resolverDiaSeleccionado(mixed $diaQuery, Carbon $mes, array $eventos): string
    {
        if (is_string($diaQuery) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $diaQuery)) {
            $candidato = Carbon::parse($diaQuery)->startOfDay();
            if ($candidato->isSameMonth($mes)) {
                return $candidato->toDateString();
            }
        }

        $hoy = now()->startOfDay();
        if ($hoy->isSameMonth($mes)) {
            return $hoy->toDateString();
        }

        $primerConEventos = collect($eventos)->pluck('fecha')->filter()->sort()->first();
        if (is_string($primerConEventos)) {
            return $primerConEventos;
        }

        return $mes->toDateString();
    }
}
