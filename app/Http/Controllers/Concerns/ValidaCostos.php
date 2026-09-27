<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CategoriasCosto;
use App\Models\Moneda;
use App\Models\MovimientosCosto;
use App\Models\TiposCambio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lo que los costos de una orden y los de un proceso hacen igual.
 *
 * Son dos pantallas distintas (una cuelga de la orden y otra del proceso),
 * pero los importes, el tipo de cambio y las reglas son los mismos. Si se
 * duplicaran, el dia que cambie la regla del tipo de cambio una dejaria de
 * cumplirla sin que nadie se entere: es el problema que la documentacion
 * pide evitar al centralizar el calculo de equivalentes NIO.
 */
trait ValidaCostos
{
    /**
     * Reglas de un movimiento de costo.
     *
     * La orden y el proceso no se validan aqui: vienen de la ruta, y el
     * proceso ya se ha comprobado que pertenece a esa orden. Aceptarlos
     * del formulario permitiria colar un costo en una OT que no es.
     *
     * Los importes calculados (costo_total y sus equivalentes en NIO) no
     * se validan porque no se reciben: los pone el servidor.
     *
     * @return array<string, array<int, mixed>>
     */
    private function validarCosto(Request $request, ?int $costoId = null): array
    {
        return $request->validate(
            [
                'categoria_costo_id' => [
                    'required',
                    'integer',
                    /*
                     * ParaRegistrar() excluye las categorias automaticas: la
                     * mano de obra y la depreciacion las calcula el sistema
                     * y escribirlas aqui las contaria dos veces. La regla
                     * sale del catalogo, no de una lista de nombres en este
                     * archivo.
                     */
                    Rule::exists('categorias_costos', 'id')
                        ->whereNull('deleted_at')
                        ->where('estado', true)
                        ->where('automatica', false),
                ],

                'fecha' => [
                    'required',
                    'date',
                ],

                'descripcion' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'cantidad' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'costo_unitario' => [
                    'required',
                    'numeric',
                    'gte:0',
                ],

                'moneda_id' => [
                    'required',
                    'integer',
                    Rule::exists('monedas', 'id')
                        ->whereNull('deleted_at')
                        ->where('estado', true),
                ],

                'observaciones' => [
                    'nullable',
                    'string',
                ],
            ],
            [
                'categoria_costo_id.required' => 'Debe seleccionar la categoría del costo.',
                'categoria_costo_id.exists' => 'Esa categoría no existe, está inactiva o ya la calcula el sistema.',
                'fecha.required' => 'Indique la fecha del costo.',
                'descripcion.required' => 'Describa el costo.',
                'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
                'cantidad.required' => 'Indique la cantidad.',
                'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
                'costo_unitario.required' => 'Indique el costo unitario.',
                'costo_unitario.gte' => 'El costo unitario no puede ser negativo.',
                'moneda_id.required' => 'Seleccione la moneda del costo.',
            ]
        );
    }

    /**
     * Arma el movimiento con los importes ya calculados.
     *
     * Si se pasa un movimiento existente se completa ese mismo, para que
     * save() actualice la fila y no inserte una nueva. Crear uno nuevo en
     * una edicion perderia el id y dejaria dos filas: la vieja, que
     * nadie borro, y la nueva.
     *
     * Devuelve tambien el tipo de cambio usado, o null cuando no habia
     * ninguno para esa fecha. El null no se avisa aqui: cada pantalla
     * decide si lo cuenta como falta o lo deja pasar, y por eso se
     * devuelve en vez de resolverse dentro.
     *
     * @param  array<string, mixed>  $validado
     * @return array{0: MovimientosCosto, 1: float|null}
     */
    private function armarCosto(
        array $validado,
        int $ordenTrabajoId,
        ?int $procesoOrdenId,
        ?MovimientosCosto $costo = null
    ): array {
        $costo ??= new MovimientosCosto();

        $costo->fill($validado);

        // La orden y el proceso salen de la ruta, nunca del formulario
        $costo->orden_trabajo_id = $ordenTrabajoId;
        $costo->proceso_orden_id = $procesoOrdenId;

        $tipoCambio = $this->tipoCambioPara(
            (int) $validado['moneda_id'],
            $validado['fecha']
        );

        $costo->calcularTotales($tipoCambio);

        return [$costo, $tipoCambio];
    }

    /**
     * Tipo de cambio aplicable, o null si no hay uno para esa fecha.
     *
     * En una moneda base el valor es 1 por definicion, sin consultar
     * tipos_cambio: el tipo de cambio de la moneda base contra si misma
     * siempre es uno, y dejarlo como null haria que la pantalla pidiera
     * uno que no hace falta.
     */
    private function tipoCambioPara(int $monedaId, string $fecha): ?float
    {
        $moneda = Moneda::find($monedaId);

        if ($moneda === null) {
            return null;
        }

        if ($moneda->es_moneda_base) {
            return 1.0;
        }

        return TiposCambio::vigentePara($monedaId, $fecha);
    }

    /**
     * El proceso de un movimiento tiene que ser el de la url, y el de una
     * orden tiene que no tener proceso.
     *
     * @throws ValidationException
     */
    private function validarPertenencia(
        MovimientosCosto $costo,
        ?int $procesoOrdenId
    ): void {
        $costo->validarProcesoPertenece();

        if ((int) $costo->proceso_orden_id !== (int) $procesoOrdenId) {
            throw ValidationException::withMessages([
                'categoria_costo_id' => 'Ese movimiento de costo no pertenece a esta pantalla.',
            ]);
        }
    }

    /**
     * Datos de los combos del formulario.
     *
     * @return array{categorias: \Illuminate\Support\Collection, monedas: \Illuminate\Support\Collection}
     */
    private function datosDeCombos(): array
    {
        return [
            'categorias' => CategoriasCosto::paraRegistrar()->get(),
            'monedas' => Moneda::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(),
        ];
    }
}
