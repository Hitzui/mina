<?php

namespace App\Http\Controllers;

use App\DataTables\EtapaDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Etapa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class EtapaController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('etapas');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(EtapaDataTable $dataTable)
    {
        $title = "Etapas de OT";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Listado de Etapas'],
        ];

        return $dataTable->render(
            'admin.etapas.index',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = "Ingresar Etapa";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Listado de Etapas',
                'url' => route('admin.etapas.index')
            ],
            ['label' => 'Ingresar Etapa'],
        ];

        $ultimoOrden = Etapa::query()
            ->whereNull('deleted_at')
            ->max('orden');

        $siguienteOrden = ($ultimoOrden ?? 0) + 1;

        return view(
            'admin.etapas.create',
            compact(
                'title',
                'breadcrumbs',
                'siguienteOrden'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'nombre' => [
                    'required',
                    'string',
                    'max:100',
                ],
                'descripcion' => ['nullable', 'string',],
                'orden' => [
                    'required',
                    'integer',
                    'min:1',
                    Rule::unique('etapas', 'orden')
                        ->where(function ($query) {
                            return $query
                                ->where('estado', true)
                                ->whereNull('deleted_at');
                        }),
                ],
                'estado' => ['required', 'boolean',],
            ],
            [
                'nombre.required' => 'El nombre de la etapa es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
                'orden.required' => 'El orden de la etapa es obligatorio.',
                'orden.integer' => 'El orden debe ser un número entero.',
                'orden.min' => 'El orden debe ser mayor o igual a 1.',
                'orden.unique' => 'Ya existe una etapa activa con este orden.',
                'estado.required' => 'Debe indicar el estado de la etapa.',
                'estado.boolean' => 'El estado seleccionado no es válido.',
            ]
        );

        Etapa::create($validated);

        Alert::toast('Etapa ingresada correctamente.')->success()->flash();

        return redirect()->route('admin.etapas.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Etapa $etapa)
    {
        $title = "Información de Etapa";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Listado de Etapas',
                'url' => route('admin.etapas.index')
            ],
            ['label' => 'Información de Etapa'],
        ];

        return view(
            'admin.etapas.show',
            compact('title', 'breadcrumbs', 'etapa')
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Etapa $etapa)
    {
        $title = "Editar Etapa";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Listado de Etapas',
                'url' => route('admin.etapas.index')
            ],
            [
                'label' => 'Información de Etapa',
                'url' => route('admin.etapas.show', $etapa)
            ],
            ['label' => 'Editar Etapa'],
        ];

        return view(
            'admin.etapas.edit',
            compact('title', 'breadcrumbs', 'etapa')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Etapa $etapa)
    {
        $validated = $request->validate(
            [
                'nombre' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                ],

                'orden' => [
                    'required',
                    'integer',
                    'min:1',
                    Rule::unique('etapas', 'orden')
                        ->where(function ($query) use ($etapa) {
                            return $query
                                ->where('estado', true)
                                ->whereNull('deleted_at')
                                ->where('id', '!=', $etapa->id);
                        }),
                ],
                'estado' => [
                    'required',
                    'boolean',
                ],
            ],
            [
                'nombre.required' =>
                    'El nombre de la etapa es obligatorio.',

                'nombre.max' =>
                    'El nombre no puede superar los 100 caracteres.',

                'orden.required' =>
                    'El orden de la etapa es obligatorio.',

                'orden.integer' =>
                    'El orden debe ser un número entero.',

                'orden.min' =>
                    'El orden debe ser mayor o igual a 1.',

                'orden.unique' =>
                    'Ya existe otra etapa activa con este orden.',

                'estado.required' =>
                    'Debe indicar el estado de la etapa.',

                'estado.boolean' =>
                    'El estado seleccionado no es válido.',
            ]
        );

        $etapa->update($validated);

        Alert::toast(
            'Etapa modificada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'admin.etapas.show',
            $etapa
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Etapa $etapa)
    {
        $etapa->delete();

        Alert::toast(
            'Etapa eliminada correctamente.'
        )->success()->flash();

        return redirect()->route('admin.etapas.index');
    }
}
