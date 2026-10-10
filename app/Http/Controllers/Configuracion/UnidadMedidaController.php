<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\UnidadesMedidaDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnidadMedidaController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.unidades_medida', ['index', 'show']);
    }

    public function index(UnidadesMedidaDataTable $dataTable)
    {
        return $dataTable->render('configuracion.unidades_medida.index', [
            'title' => 'Unidades de medida',
            'breadcrumbs' => [
                ['label' => 'Inicio', 'url' => route('home')],
                ['label' => 'Configuración'],
                ['label' => 'Unidades de medida'],
            ],
        ]);
    }

    public function create()
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $unidad = UnidadMedida::create($this->validar($request));

        return response()->json(['ok' => true, 'id' => $unidad->id]);
    }

    public function show(UnidadMedida $unidadMedida)
    {
        return response()->json($this->datos($unidadMedida));
    }

    public function edit(UnidadMedida $unidadMedida)
    {
        return response()->json($this->datos($unidadMedida));
    }

    public function update(Request $request, UnidadMedida $unidadMedida)
    {
        $datos = $this->validar($request, $unidadMedida);
        if ($datos['nombre'] !== $unidadMedida->nombre && ! $unidadMedida->puedeEliminarse()) {
            throw ValidationException::withMessages([
                'nombre' => 'No se puede cambiar el nombre de una unidad asociada a registros; puede desactivarla.',
            ]);
        }
        if (! $datos['estado'] && ! $unidadMedida->puedeEliminarse()) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede desactivar una unidad asociada a registros. Use otra unidad en los nuevos registros y vuelva a intentarlo.',
            ]);
        }
        $unidadMedida->update($datos);

        return response()->json(['ok' => true, 'id' => $unidadMedida->id]);
    }

    public function destroy(UnidadMedida $unidadMedida)
    {
        if (! $unidadMedida->puedeEliminarse()) {
            throw ValidationException::withMessages([
                'nombre' => 'La unidad está asociada a registros y no se puede eliminar. Puede desactivarla.',
            ]);
        }

        $unidadMedida->delete();

        return response()->json(['ok' => true]);
    }

    private function datos(UnidadMedida $unidad): array
    {
        return ['id' => $unidad->id, 'nombre' => $unidad->nombre, 'estado' => (bool) $unidad->estado];
    }

    private function validar(Request $request, ?UnidadMedida $unidad = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:40', Rule::unique('unidades_medida', 'nombre')->ignore($unidad?->id)],
            'estado' => ['required', 'boolean'],
        ], [
            'nombre.required' => 'Escriba el nombre de la unidad.',
            'nombre.unique' => 'Ya existe una unidad con ese nombre.',
            'estado.required' => 'Seleccione el estado de la unidad.',
        ]);
        $datos['estado'] = (bool) $datos['estado'];

        return $datos;
    }
}
