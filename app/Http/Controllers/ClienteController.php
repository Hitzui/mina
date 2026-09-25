<?php

namespace App\Http\Controllers;

use App\DataTables\ClienteDataTable;
use App\DataTables\ClienteSelectorDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Cliente;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ClienteController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('clientes');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ClienteDataTable $dataTable)
    {
        $title = "Listado de Clientes";
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Clientes']
        ];
        return $dataTable->render('admin.clientes.index', compact('title', 'breadcrumbs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = "Ingresar Cliente";
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Clientes', 'url' => route('admin.clientes.index')],
            ['label' => 'Ingresar Clientes'],
        ];
        return view('admin.clientes.create', compact('title', 'breadcrumbs'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'boolean'],
        ]);

        Cliente::create($validated);
        Alert::toast('Cliente ingresado correctamente.')->success()->flash();
        return redirect()->route('admin.clientes.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cliente = Cliente::findOrFail($id);
        if(!$cliente){
            Alert::toast('No se encontro el cliente', 'danger')->error()->flash();
            return redirect()->route('admin.clientes.index');
        }
        $title = "Datos del Cliente";
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Clientes', 'url' => route('admin.clientes.index')],
            ['label' => 'Datos del Cliente '.$cliente->id],
        ];
        return view('admin.clientes.show', compact('cliente', 'title', 'breadcrumbs'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cliente $cliente)
    {
        $title = "Editar Cliente";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Clientes', 'url' => route('admin.clientes.index')],
            ['label' => 'Datos del cliente'.$cliente->id, 'url' => route('admin.clientes.show', $cliente->id)],
            ['label' => 'Editar Cliente'],
        ];

        return view(
            'admin.clientes.edit',
            compact('title', 'breadcrumbs', 'cliente')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'boolean'],
        ]);

        $cliente->update($validated);

        Alert::toast('Cliente actualizado correctamente.', 'success')
            ->flash();

        return redirect()->route('admin.clientes.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cliente = Cliente::findOrFail($id);
        if(!$cliente){
            Alert::toast('No se encontro el cliente', 'danger')->error()->flash();
        }
        $cliente->delete();
        Alert::toast('Cliente eliminado correctamente', 'success')->success()->flash();
        return redirect()->route('admin.clientes.index');
    }

    public function selector(ClienteSelectorDataTable $dataTable)
    {
        return $dataTable->ajax();
    }
}
