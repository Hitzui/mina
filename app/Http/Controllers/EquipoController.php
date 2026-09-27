<?php

namespace App\Http\Controllers;

use App\DataTables\EquiposDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Equipo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Equipos que usa el taller y que se deprecia con el tiempo.
 *
 * El equipo es dato maestro: su valor y su vida util determinan la
 * depreciacion de cada proceso donde se use, pero el costo ya registrado
 * no se mueve despues (ver ProcesoEquipo).
 */
class EquipoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('equipos');
    }

    public function index(EquiposDataTable $dataTable)
    {
        $title = 'Equipos';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Equipos'],
        ];

        return $dataTable->render(
            'admin.equipos.index',
            compact('title', 'breadcrumbs')
        );
    }

    public function create()
    {
        $title = 'Nuevo Equipo';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Equipos',
                'url' => route('admin.equipos.index')
            ],
            ['label' => 'Nuevo Equipo'],
        ];

        return view(
            'admin.equipos.create',
            compact('title', 'breadcrumbs')
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validar($request);

        Equipo::create($validated);

        Alert::toast('Equipo creado correctamente.')->success()->flash();

        return redirect()->route('admin.equipos.index');
    }

    public function show(Equipo $equipo)
    {
        $title = 'Información del Equipo';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Equipos',
                'url' => route('admin.equipos.index')
            ],
            ['label' => $equipo->nombre],
        ];

        // Los procesos donde se uso, para ver cuanto harentizado cada uno
        $asignaciones = $equipo->asignaciones()
            ->with('procesoOrden.orden_trabajo')
            ->orderByDesc('fecha_inicio')
            ->get();

        // Se cuelgan como relacion cargada: asi el total de depreciacion
        // acumulada se saca de esta misma lista y no hace falta una
        // consulta mas
        $equipo->setRelation('asignaciones', $asignaciones);

        return view(
            'admin.equipos.show',
            compact('title', 'breadcrumbs', 'equipo', 'asignaciones')
        );
    }

    public function edit(Equipo $equipo)
    {
        $title = 'Editar Equipo';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Equipos',
                'url' => route('admin.equipos.index')
            ],
            [
                'label' => $equipo->nombre,
                'url' => route('admin.equipos.show', $equipo)
            ],
            ['label' => 'Editar Equipo'],
        ];

        return view(
            'admin.equipos.edit',
            compact('title', 'breadcrumbs', 'equipo')
        );
    }

    public function update(Request $request, Equipo $equipo)
    {
        $validated = $this->validar($request, $equipo);

        /*
         * Aviso, no bloqueo: tocar el valor o la vida util cambia la
         * depreciacion de los usos que se registren de aqui en adelante,
         * pero no la de los ya registrados, que guardaron su propia foto.
         */
        $cambiaLaTasa = $this->cambiaLaTasa($equipo, $validated);

        $equipo->update($validated);

        $mensaje = 'Equipo actualizado correctamente.';

        if ($cambiaLaTasa) {
            $mensaje .= ' La depreciación diaria cambió: solo afecta a los '
                . 'usos de equipos que se registren desde ahora; los costos '
                . 'ya registrados en procesos anteriores se mantienen.';
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route(
            'admin.equipos.show',
            $equipo
        );
    }

    /**
     * Un equipo con historial de procesos no se borra.
     *
     * Las asignaciones son parte de la trazabilidad economica de los
     * procesos, asi que la clave foranea esta en RESTRICT. Se comprueba
     * aqui para poder avisar con un mensaje util en vez de dejar que
     * reviente la base.
     */
    public function destroy(Equipo $equipo)
    {
        $procesos = $equipo->asignaciones()
            ->with('procesoOrden.orden_trabajo')
            ->orderByDesc('fecha_inicio')
            ->get();

        if ($procesos->isNotEmpty()) {
            $primero = $procesos->first()->procesoOrden;
            $orden = $primero?->orden_trabajo;

            Alert::toast(
                'No se puede eliminar el equipo porque ya se usó en '
                . $procesos->count()
                . ' proceso(s). Primero debe quitarse de esos procesos; '
                . 'el registro histórico del costo se conserva. '
                . 'Primer uso: '
                . ($orden?->codigo ?? '—')
                . ' / '
                . ($primero?->codigo ?? '—')
                . '.'
            )->error()->flash();

            return redirect()->route('admin.equipos.show', $equipo);
        }

        $equipo->delete();

        Alert::toast('Equipo eliminado correctamente.')->success()->flash();

        return redirect()->route('admin.equipos.index');
    }

    /**
     * Reglas del equipo.
     *
     * Las tres que importan para la depreciacion:
     *   - la vida util tiene que ser mayor que cero, si no la tasa diaria
     *     seria una division entre cero;
     *   - el valor residual no puede pasar del valor de adquisicion, si no
     *     el depreciable daria negativo;
     *   - la depreciacion acumulada no puede pasar de lo que queda.
     */
    private function validar(Request $request, ?Equipo $equipo = null): array
    {
        return $request->validate(
            [
                'codigo' => [
                    'required',
                    'string',
                    'max:30',
                    Rule::unique('equipos', 'codigo')
                        ->whereNull('deleted_at')
                        ->where(
                            fn($q) => $equipo
                                ? $q->where('id', '!=', $equipo->id)
                                : $q
                        ),
                ],

                'nombre' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'fecha_adquisicion' => [
                    'nullable',
                    'date',
                ],

                'valor_adquisicion' => [
                    'required',
                    'numeric',
                    'gte:0',
                ],

                'valor_residual' => [
                    'required',
                    'numeric',
                    'gte:0',
                    'lte:valor_adquisicion',
                ],

                'vida_util_meses' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:600',
                ],

                'estado' => [
                    'required',
                    'boolean',
                ],
            ],
            [
                'codigo.required' => 'El código del equipo es obligatorio.',
                'codigo.max' => 'El código no puede superar los 30 caracteres.',
                'codigo.unique' => 'Ya existe un equipo activo con este código.',
                'nombre.required' => 'El nombre del equipo es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
                'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
                'valor_adquisicion.required' => 'El valor de adquisición es obligatorio.',
                'valor_adquisicion.numeric' => 'El valor de adquisición debe ser un número.',
                'valor_adquisicion.gte' => 'El valor de adquisición no puede ser negativo.',
                'valor_residual.required' => 'El valor residual es obligatorio.',
                'valor_residual.lte' => 'El valor residual no puede ser mayor que el valor de adquisición.',
                'vida_util_meses.required' => 'La vida útil en meses es obligatoria.',
                'vida_util_meses.integer' => 'La vida útil debe ser un número entero de meses.',
                'vida_util_meses.min' => 'La vida útil debe ser de al menos 1 mes; con cero meses el equipo no depreciaría.',
                'vida_util_meses.max' => 'La vida útil no puede superar los 600 meses (50 años).',
                'estado.required' => 'Debe indicar el estado del equipo.',
            ]
        );
    }

    /**
     * Si el cambio mueve la tasa diaria de depreciacion.
     */
    private function cambiaLaTasa(Equipo $equipo, array $validated): bool
    {
        $antes = (float) $equipo->depreciacionDiaria();

        $despues = (new Equipo([
            'valor_adquisicion' => $validated['valor_adquisicion'],
            'valor_residual' => $validated['valor_residual'],
            'vida_util_meses' => $validated['vida_util_meses'],
        ]))->depreciacionDiaria();

        return abs($antes - $despues) > 0.0001;
    }
}
