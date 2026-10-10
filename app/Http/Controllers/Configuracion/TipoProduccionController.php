<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\TiposProduccionDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\TiposProduccion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class TipoProduccionController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.tipos_produccion', ['index', 'show']);
        $this->middleware('permission:configuracion.tipos_produccion.edit')->only('cambiarEstado');
    }

    public function index(TiposProduccionDataTable $dataTable)
    {
        return $dataTable->render('configuracion.tipos_produccion.index', [
            'title' => 'Tipos de producción',
            'breadcrumbs' => [
                ['label' => 'Inicio', 'url' => route('home')],
                ['label' => 'Configuración'],
                ['label' => 'Tipos de producción'],
            ],
        ]);
    }

    public function create()
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $tipo = TiposProduccion::create($this->validar($request));

        Alert::success('Tipo de producción guardado');

        return response()->json(['ok' => true, 'id' => $tipo->id]);
    }

    public function show(TiposProduccion $tipoProduccion)
    {
        return response()->json($this->datosParaModal($tipoProduccion));
    }

    public function edit(TiposProduccion $tipoProduccion)
    {
        return response()->json($this->datosParaModal($tipoProduccion));
    }

    public function update(Request $request, TiposProduccion $tipoProduccion)
    {
        $tipoProduccion->update($this->validar($request));

        Alert::success('Tipo de producción guardado');

        return response()->json(['ok' => true, 'id' => $tipoProduccion->id]);
    }

    public function cambiarEstado(TiposProduccion $tipoProduccion)
    {
        $tipoProduccion->update([
            TiposProduccion::ESTADO => ! $tipoProduccion->estado,
        ]);

        Alert::success($tipoProduccion->estado
            ? 'Tipo de producción activado'
            : 'Tipo de producción desactivado');

        return response()->json(['ok' => true]);
    }

    public function destroy(TiposProduccion $tipoProduccion)
    {
        if (! $tipoProduccion->sePuedeBorrar()) {
            throw ValidationException::withMessages([
                TiposProduccion::NOMBRE => 'No se puede borrar: este tipo de producción está en uso, en '
                    .$tipoProduccion->fraseDeUsos().'. Se puede desactivar para retirarlo de las nuevas producciones.',
            ]);
        }

        $tipoProduccion->delete();

        Alert::success('Tipo de producción eliminado');

        return response()->json(['ok' => true]);
    }

    private function datosParaModal(TiposProduccion $tipoProduccion): array
    {
        $usos = $tipoProduccion->usos();

        return [
            'id' => $tipoProduccion->id,
            'nombre' => $tipoProduccion->nombre,
            'descripcion' => $tipoProduccion->descripcion,
            'estado' => (bool) $tipoProduccion->estado,
            'usos' => $usos,
            'frase_de_usos' => $tipoProduccion->fraseDeUsos(),
            'se_puede_borrar' => $usos === [],
            'url_editar' => route('configuracion.tipos_produccion.edit', $tipoProduccion),
        ];
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            TiposProduccion::NOMBRE => ['required', 'string', 'max:100'],
            TiposProduccion::DESCRIPCION => ['nullable', 'string', 'max:255'],
            TiposProduccion::ESTADO => ['required', 'in:0,1'],
        ], [
            TiposProduccion::NOMBRE.'.required' => 'Escriba el nombre del tipo de producción.',
            TiposProduccion::NOMBRE.'.max' => 'El nombre no puede pasar de 100 caracteres.',
            TiposProduccion::DESCRIPCION.'.max' => 'La descripción no puede pasar de 255 caracteres.',
            TiposProduccion::ESTADO.'.required' => 'Diga si el tipo está activo o inactivo.',
            TiposProduccion::ESTADO.'.in' => 'El estado tiene que ser activo o inactivo.',
        ]);

        $datos[TiposProduccion::ESTADO] = (bool) $datos[TiposProduccion::ESTADO];
        $descripcion = $datos[TiposProduccion::DESCRIPCION] ?? null;
        $datos[TiposProduccion::DESCRIPCION] = ($descripcion === '' || $descripcion === null)
            ? null
            : $descripcion;

        return $datos;
    }
}
