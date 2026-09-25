<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\CategoriasCostoDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\CategoriasCosto;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class CategoriaCostoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.categorias_costos');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(CategoriasCostoDataTable $dataTable)
    {
        $title = 'Categorías de Costos';

        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Categorías de Costos',
                'url' => '#',
            ],
        ];

        return $dataTable->render(
            'configuracion.categorias_costos.index',
            compact(
                'title',
                'breadcrumbs'
            )
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = 'Nueva Categoría de Costo';

        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Categorías de Costos',
                'url' => route(
                    'configuracion.categorias_costos.index'
                ),
            ],
            [
                'label' => 'Nueva Categoría',
                'url' => '#',
            ],
        ];

        return view(
            'configuracion.categorias_costos.create',
            compact(
                'title',
                'breadcrumbs'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
            'estado' => [
                'required',
                'boolean',
            ],
        ]);

        $categoriaCosto = CategoriasCosto::create($validated);

        Alert::toast(
            'Categoría de costo creada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'configuracion.categorias_costos.show', $categoriaCosto->id
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $title = 'Categoría de Costo';
        $categoriaCosto = CategoriasCosto::findOrFail($id);
        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Categorías de Costos',
                'url' => route(
                    'configuracion.categorias_costos.index'
                ),
            ],
            [
                'label' => $categoriaCosto->nombre,
                'url' => '#',
            ],
        ];

        return view(
            'configuracion.categorias_costos.show',
            compact(
                'title',
                'breadcrumbs',
                'categoriaCosto'
            )
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $title = 'Editar Categoría de Costo';
        $categoriaCosto = CategoriasCosto::findOrFail($id);
        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Categorías de Costos',
                'url' => route(
                    'configuracion.categorias_costos.index'
                ),
            ],
            [
                'label' => $categoriaCosto->nombre,
                'url' => route(
                    'configuracion.categorias_costos.show',
                    $categoriaCosto
                ),
            ],
            [
                'label' => 'Editar',
                'url' => '#',
            ],
        ];

        return view(
            'configuracion.categorias_costos.edit',
            compact(
                'title',
                'breadcrumbs',
                'categoriaCosto'
            )
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        Request $request,
        string  $id
    )
    {
        $categoriaCosto = CategoriasCosto::findOrFail($id);
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:100',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
            'estado' => [
                'required',
                'boolean',
            ],
        ]);

        $categoriaCosto->update($validated);

        Alert::toast(
            'Categoría de costo actualizada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'configuracion.categorias_costos.show',
            $categoriaCosto
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $categoriaCosto = CategoriasCosto::findOrFail($id);
        $categoriaCosto->delete();

        Alert::toast(
            'Categoría de costo eliminada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'configuracion.categorias_costos.index'
        );
    }
}
