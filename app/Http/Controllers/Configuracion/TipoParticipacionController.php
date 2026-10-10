<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\TiposParticipacionDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\TiposParticipacion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TipoParticipacionController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.tipos_participacion', ['index', 'show']);
        $this->middleware('permission:configuracion.tipos_participacion.edit')->only('cambiarEstado');
    }

    public function index(TiposParticipacionDataTable $dataTable)
    {
        return $dataTable->render('configuracion.tipos_participacion.index', [
            'title' => 'Tipos de participación',
            'breadcrumbs' => [
                ['label' => 'Inicio', 'url' => route('home')],
                ['label' => 'Configuración'],
                ['label' => 'Tipos de participación'],
            ],
        ]);
    }

    public function create()
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $tipo = TiposParticipacion::create($this->validar($request));

        return response()->json(['ok' => true, 'id' => $tipo->id]);
    }

    public function show(TiposParticipacion $tipoParticipacion)
    {
        return response()->json($this->datos($tipoParticipacion));
    }

    public function edit(TiposParticipacion $tipoParticipacion)
    {
        return response()->json($this->datos($tipoParticipacion));
    }

    public function update(Request $request, TiposParticipacion $tipoParticipacion)
    {
        $tipoParticipacion->update($this->validar($request));

        return response()->json(['ok' => true, 'id' => $tipoParticipacion->id]);
    }

    public function cambiarEstado(TiposParticipacion $tipoParticipacion)
    {
        $tipoParticipacion->update([
            TiposParticipacion::ESTADO => ! $tipoParticipacion->estado,
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(TiposParticipacion $tipoParticipacion)
    {
        if (! $tipoParticipacion->sePuedeBorrar()) {
            throw ValidationException::withMessages([
                TiposParticipacion::NOMBRE => 'No se puede borrar: este tipo de participación está en uso, en '
                    .$tipoParticipacion->fraseDeUsos().'. Se puede desactivar para retirarlo de nuevas cotizaciones y acuerdos.',
            ]);
        }

        $tipoParticipacion->delete();

        return response()->json(['ok' => true]);
    }

    private function datos(TiposParticipacion $tipo): array
    {
        $usos = $tipo->usos();

        return [
            'id' => $tipo->id,
            'nombre' => $tipo->nombre,
            'descripcion' => $tipo->descripcion,
            'estado' => (bool) $tipo->estado,
            'usos' => $usos,
            'frase_de_usos' => $tipo->fraseDeUsos(),
            'se_puede_borrar' => $usos === [],
            'url_editar' => route('configuracion.tipos_participacion.edit', $tipo),
        ];
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:0,1'],
        ], [
            'nombre.required' => 'Escriba el nombre del tipo de participación.',
            'nombre.max' => 'El nombre no puede pasar de 100 caracteres.',
            'descripcion.max' => 'La descripción no puede pasar de 255 caracteres.',
            'estado.required' => 'Diga si el tipo está activo o inactivo.',
            'estado.in' => 'El estado tiene que ser activo o inactivo.',
        ]);

        $datos['estado'] = (bool) $datos['estado'];
        $datos['descripcion'] = filled($datos['descripcion'] ?? null) ? $datos['descripcion'] : null;

        return $datos;
    }
}
