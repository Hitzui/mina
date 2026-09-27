<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Moneda;
use App\Models\MovimientosInventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas de los movimientos de material, compartidas por la pantalla
 * del almacen y la del proceso.
 *
 * estan en un trait y no en un controlador porque las dos pantallas
 * registran el mismo tipo de fila: si cada una tuviera las suyas, la
 * diferencia entre una validacion y otra seria justo la clase de error
 * que nadie nota hasta que el saldo no cuadra.
 */
trait ValidaMovimientos
{
    /**
     * Comprueba lo que llega del formulario.
     *
     * El costo unitario no se pide: lo pone el inventario. Es lo unico que
     * se aseguro con el cliente, asi que ni siquiera se valida, para que no
     * se meta la idea de que se puede cambiar.
     *
     * @return array
     */
    protected function validarMovimiento(
        Request $request,
        ?int $ignorarId = null
    ): array {
        return $request->validate([
            /*
             * El material tiene que existir, no estar borrado y estar
             * activo. La ultima condicion va en la regla y no solo en el
             * desplegable: si se dejara solo en la pantalla, un material
             * desactivado se podria seguir consumiendo escribiendo su id en
             * la peticion, que es justo lo que se quiere impedir.
             */
            'producto_id' => [
                'required',
                Rule::exists('productos', 'id')
                    ->whereNull('deleted_at')
                    ->where('estado', true),
            ],
            'tipo' => ['required', 'string', 'max:40'],
            'fecha' => ['required', 'date'],
            'cantidad' => ['required', 'numeric', 'gt:0'],

            // Solo para las entradas. En una salida los pisa el inventario.
            'costo_unitario' => ['nullable', 'numeric', 'min:0'],
            'moneda_id' => ['nullable', Rule::exists('monedas', 'id')],

            'referencia' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'producto_id.required' => 'Elija el material que entra o sale.',
            'producto_id.exists' => 'El material elegido no existe o esta inactivo.',
            'tipo.required' => 'Indique si el material entra o sale.',
            'fecha.required' => 'Indique la fecha del movimiento.',
            'cantidad.required' => 'Indique cuanto material.',
            'cantidad.gt' => 'La cantidad tiene que ser mayor que cero.',
            'costo_unitario.min' => 'El costo unitario no puede ser negativo.',
            'referencia.max' => 'La referencia es demasiado larga.',
            'observaciones.max' => 'Las observaciones son demasiado largas.',
        ]);
    }

    /**
     * Comprueba que el tipo sea uno del catalogo.
     *
     * La columna es texto libre, asi que sin esto se podrian guardar tipos
     * que no mueven el saldo: el material entraria o saldria del kardex sin
     * tocar las existencias, y el desglose no lo notaria.
     */
    protected function validarTipo(array $validado): void
    {
        $movimiento = new MovimientosInventario();
        $movimiento->tipo = $validado['tipo'];

        if ($movimiento->tipoNormalizado() === null) {
            throw ValidationException::withMessages([
                'tipo' => 'Ese tipo de movimiento no existe.',
            ]);
        }
    }

    /**
     * Los materiales activos, para el desplegable.
     *
     * Se mandan con su existencia y su costo promedio, para que la pantalla
     * pueda avisar antes de dejar registrar un consumo que no cabe.
     *
     * @return Collection<int, Producto>
     */
    protected function productosParaElFormulario(): Collection
    {
        return Producto::query()
            ->where('estado', true)
            ->whereNull('deleted_at')
            ->with('inventario')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Las monedas, para el desplegable de las entradas.
     *
     * @return Collection<int, Moneda>
     */
    protected function monedasParaElFormulario(): Collection
    {
        return Moneda::query()
            ->where('estado', true)
            ->orderBy('codigo')
            ->get();
    }
}
