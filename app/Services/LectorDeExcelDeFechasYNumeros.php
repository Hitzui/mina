<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\ReaderInterface;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class LectorDeExcelDeFechasYNumeros
{
    /**
     * Nombres que se aceptan en la columna de la fecha.
     *
     * Se comparan sin acentos y sin espacios, para que "Fecha de operacion",
     * "FECHA" y "fecha " sean la misma columna.
     *
     * @return array<int, string>
     */
    abstract protected function nombresDeFecha(): array;

    /**
     * Nombres que se aceptan en la columna del numero.
     *
     * @return array<int, string>
     */
    abstract protected function nombresDeValor(): array;

    /**
     * Como se llama el numero para el usuario, en minuscula y sin acentos.
     *
     * Solo sale en los avisos, y es para que el mensaje hable de lo que se
     * esta importando y no de "el valor", que aqui no significa nada.
     */
    abstract protected function nombreDeLoQueSeImporta(): string;

    /**
     * Cuantas filas se miran para encontrar la cabecera.
     *
     * Los archivos que arma Excel a mano suelen dejar un titulo o una fila de
     * autor en las dos primeras. Con cinco se llega de sobra y con veinte se
     * empieza a correr el riesgo de dar con una palabra suelta que se parezca
     * a un encabezado.
     */
    private const FILAS_BUSCANDO_CABECERA = 5;


    /**
     * Lee el archivo y devuelve las filas, ya con la fecha en Y-m-d.
     *
     * @return array<int, array{fecha: string, valor: float}>
     *
     * @throws \RuntimeException si el archivo no se puede leer o no trae las
     *                          dos columnas. El mensaje va escrito para que
     *                          el usuario sepa que corregir, no para que el
     *                          programador sepa que paso.
     */
    public function leer(string $ruta): array
    {
        $libro = $this->abrir($ruta);

        try {
            return $this->leerDelLibro($libro);
        } finally {
            /*
             * Aqui se sueltan las hojas del libro, que ocupan memoria. Va en
             * un finally y no antes de devolver porque con un return dentro
             * del try el finally se ejecuta igualmente, y soltar las hojas
             * antes de que el llamante las use deja la hoja devuelta sin
             * celdas: el error que sale de ahi —"no se ha inicializado"— no
             * dice nada del archivo que el usuario subio.
             */
            $libro->disconnectWorksheets();
        }
    }

    /**
     * Saca las filas de la hoja que tiene datos.
     *
     * @return array<int, array{fecha: string, valor: float}>
     */
    private function leerDelLibro(Spreadsheet $libro): array
    {
        $hoja = null;

        foreach ($libro->getWorksheetIterator() as $candidata) {
            if ($candidata->getHighestRow() > 0) {
                $hoja = $candidata;

                break;
            }
        }

        if ($hoja === null) {
            throw new \RuntimeException('El archivo no tiene hojas con datos.');
        }

        $encabezados = $this->buscarEncabezados($hoja);

        $filas = [];
        $vistos = [];
        $descartados = [];

        $ultimaFila = $hoja->getHighestRow();

        for ($fila = $encabezados['fila'] + 1; $fila <= $ultimaFila; $fila++) {
            $fecha = $this->fechaDe($hoja->getCell($encabezados['columnaFecha'] . $fila));

            $valorCrudo = $hoja->getCell($encabezados['columnaValor'] . $fila)->getValue();
            $valor = $this->aNumero($valorCrudo);

            // Una fila con las dos celdas vacias es una fila en blanco del
            // archivo, no un error: los archivos de Excel vienen con Stato.
            if ($fecha === null && $valor === null) {
                continue;
            }

            if ($fecha === null || $valor === null) {
                $descartados[] = [
                    'fila' => $fila,
                    'motivo' => $fecha === null
                        ? 'la fecha no se entiende'
                        : 'el ' . $this->nombreDeLoQueSeImporta() . ' no es un numero',
                    'fecha' => $fecha,
                    'valor' => $valorCrudo,
                ];

                continue;
            }

            /*
             * Un dia repetido dentro del MISMO archivo casi siempre es un
             * error de la conversion, no dos tipos de cambio del mismo dia.
             * Se avisa y se deja el primero, que es el que se leyo antes.
             */
            if (isset($vistos[$fecha])) {
                $descartados[] = [
                    'fila' => $fila,
                    'motivo' => 'el ' . $this->mostrar($fecha) . ' ya venia en la fila ' . $vistos[$fecha],
                    'fecha' => $fecha,
                    'valor' => $valorCrudo,
                ];

                continue;
            }

            $vistos[$fecha] = $fila;

            $filas[] = ['fecha' => $fecha, 'valor' => $valor];
        }

        if (empty($filas)) {
            throw new \RuntimeException(
                'El archivo se abrio bien pero no tiene ningun dia con fecha y '
                . $this->nombreDeLoQueSeImporta() . '. '
                . 'Revise que las dos columnas esten llenas desde la segunda fila.'
            );
        }

        usort($filas, fn(array $a, array $b) => $a['fecha'] <=> $b['fecha']);

        $this->descartadas = $descartados;

        return $filas;
    }

    /**
     * Las filas que se dejaron fuera y por que.
     *
     * Vive aqui y no como valor de retorno porque son un aviso para la
     * pantalla de confirmacion, no parte del dato: la importacion va con las
     * filas buenas, y las malas se cuentan para que el usuario sepa que el
     * archivo traia cosas que no se han metido.
     *
     * @var array<int, array{fila: int, motivo: string, fecha: ?string, valor: mixed}>
     */
    private array $descartadas = [];

    /**
     * @return array<int, array{fila: int, motivo: string, fecha: ?string, valor: mixed}>
     */
    public function descartadas(): array
    {
        return $this->descartadas;
    }

    // ------------------------------------------------------------------
    // Como se abre el archivo
    // ------------------------------------------------------------------

    /**
     * Abre el archivo y devuelve el libro entero.
     *
     * Un archivo de Excel puede traer un cartelito de "este archivo lo creo
     * Excel 2019" como primera hoja. Si se leyera esa, no habria ni una fila
     * de datos y el error que sale seria "el archivo no tiene ningun dia",
     * que no ayuda a nadie a arreglar lo que pasa. Por eso se devuelve el
     * libro y no una hoja: la busqueda de la hoja con datos la hace quien lo
     * lee, cuando ya puede mirar todas.
     */
    private function abrir(string $ruta): Spreadsheet
    {
        if (! is_file($ruta)) {
            throw new \RuntimeException('No se encuentra el archivo que se subio.');
        }

        /*
         * El formato se saca del contenido del archivo y no de su nombre.
         *
         * Dos motivos, y los dos son fallos reales:
         *
         *  - La ruta que genera Laravel para una subida es un "tmp" sin
         *    extension, asi que mirar el nombre rechaza un Excel
         *    perfectamente bueno con el aviso de que lo subido es un tmp.
         *
         *  - Y al reves tampoco vale: createReaderForFile() decide por la
         *    extension, asi que un .jpg que se renombra a .xlsx se acepta
         *    como Excel y revienta al abrirlo, con un error que no menciona
         *    el archivo. Por eso se comprueba primero que el archivo sea
         *    de verdad un Excel, leyendo su cabecera.
         */
        if (! $this->pareceExcel($ruta)) {
            throw new \RuntimeException($this->avisoDeFormato());
        }

        try {
            $lector = IOFactory::createReaderForFile($ruta);
        } catch (\Throwable $e) {
            throw new \RuntimeException($this->avisoDeFormato());
        }

        try {
            /*
             * Sin la hoja de calculo en memoria ni con formulas: el archivo
             * del banco trae las dos cosas, y sin esto una celda con formula
             * se leeria como "=REDONDEAR(...)".
             *
             * El segundo parametro de load() es un entero de banderas vacio:
             * en esta version tiene que ser int, y si se le pasa null avisa
             * antes incluso de abrir el archivo.
             */
            $lector->setReadDataOnly(true);

            return $lector->load($ruta, 0, false, false, false);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'El archivo se reconoce como Excel pero no se pudo abrir. '
                . 'Compruebe que no este dañado y que no este protegido con '
                . 'contraseña. Detalle: ' . $e->getMessage()
            );
        }
    }

    /**
     * Si el archivo es de verdad un Excel, mirando lo que tiene dentro.
     *
     * Un Excel moderno —xlsx— es un zip: un archivo que empieza por la firma
     * PK. Un xls viejo empieza por unos bytes fijos. Un csv es texto. Lo que
     * no es ninguna de esas tres cosas no es un Excel, por mas que se llame
     * asi.
     *
     * La comprobacion va por el contenido y no por el nombre porque el
     * nombre lo pone quien sube el archivo y el contenido no. Y porque
     * createReaderForFile() decide por el nombre, con lo que una foto
     * renombrada a .xlsx pasaria el filtro de extension y reventaria al
     * abrirla.
     */
    private function pareceExcel(string $ruta): bool
    {
        $manejador = @fopen($ruta, 'rb');

        if ($manejador === false) {
            return false;
        }

        $cabecera = (string) fread($manejador, 512);

        fclose($manejador);

        /*
         * Un archivo sin un solo byte esta vacio, y no es un Excel: es un
         * Excel que no llego a guardarse, que es lo que pasa cuando se
         * arrastra un archivo que el navegador todavia no ha bajado.
         *
         * Sin este caso caeria en la regla de los archivos de texto —cero
         * bytes, ni un nulo, luego parece csv— y pasaria el filtro para
         * fallar despues con "no tiene ningun dia con fecha y valor", que
         * hace pensar que el archivo traia dias mal puesta la fecha.
         */
        if ($cabecera === '') {
            return false;
        }

        // Un xlsx o un xlsm son un zip: empiezan por PK
        if (str_starts_with($cabecera, "PK")) {
            return true;
        }

        // Un xls viejo tiene esta firma en los primeros bytes
        if (str_starts_with($cabecera, "\xD0\xCF\x11\xE0")) {
            return true;
        }

        /*
         * Un csv u ods en texto. Un ods tambien es un zip, asi que ya ha
         * entrado por arriba. Aqui solo queda el csv, que se reconoce por no
         * traer un binario en los primeros bytes: si hay un byte nulo, no es
         * texto.
         */
        return ! str_contains(substr($cabecera, 0, 64), "\0");
    }

    /**
     * El aviso cuando el archivo no es un Excel, escrito para que el usuario
     * sepa que hacer y no solo que fallo.
     */
    private function avisoDeFormato(): string
    {
        return 'El archivo no es un Excel que se pueda leer. Solo se aceptan xlsx, '
            . 'xls, xlsm, csv y ods. Si es un Excel de verdad y sigue fallando, '
            . 'puede estar protegido con contraseña o dañado.';
    }

    // ------------------------------------------------------------------
    // Como se encuentra la fila de encabezados
    // ------------------------------------------------------------------

    /**
     * Busca la fila que dice de que es cada columna.
     *
     * @return array{fila: int, columnaFecha: string, columnaValor: string}
     */
    private function buscarEncabezados(Worksheet $hoja): array
    {
        $ultimaFila = min($hoja->getHighestRow(), self::FILAS_BUSCANDO_CABECERA);
        $ultimaColumna = Coordinate::columnIndexFromString($hoja->getHighestColumn());

        for ($fila = 1; $fila <= $ultimaFila; $fila++) {
            $columnaFecha = null;
            $columnaValor = null;

            for ($columna = 1; $columna <= $ultimaColumna; $columna++) {
                $letra = Coordinate::stringFromColumnIndex($columna);
                $nombre = $this->normalizar($this->textoDe($hoja->getCell($letra . $fila)->getValue()));

                if ($nombre === '') {
                    continue;
                }

                if ($columnaFecha === null && in_array($nombre, $this->nombresDeFecha(), true)) {
                    $columnaFecha = $letra;
                }

                if ($columnaValor === null && in_array($nombre, $this->nombresDeValor(), true)) {
                    $columnaValor = $letra;
                }
            }

            if ($columnaFecha !== null && $columnaValor !== null) {
                return [
                    'fila' => $fila,
                    'columnaFecha' => $columnaFecha,
                    'columnaValor' => $columnaValor,
                ];
            }
        }

        $que = $this->nombreDeLoQueSeImporta();

        throw new \RuntimeException(
            'No se encontro la fila de encabezados. Se espera una fila arriba con '
            . 'el nombre de las dos columnas: una de fecha y otra de ' . $que . '. Se '
            . 'aceptan estos nombres, da igual si van en mayusculas o con acentos: '
            . 'para la fecha, ' . implode(', ', $this->nombresDeFecha()) . '; para el '
            . $que . ', ' . implode(', ', $this->nombresDeValor()) . '.'
        );
    }

    // ------------------------------------------------------------------
    // Como se lee cada celda
    // ------------------------------------------------------------------

    /**
     * La fecha de una celda, en Y-m-d, o null si no se entiende.
     */
    private function fechaDe(Cell $celda): ?string
    {
        $valor = $this->textoDe($celda->getValue());

        if ($valor === '') {
            return null;
        }

        /*
         * Si la celda es de verdad una fecha de Excel, se usa el valor
         * interno. Es el camino de fiar: Excel guarda las fechas como un
         * numero de dias desde 1900, y ese numero no se puede confundir con
         * ningun otro dato.
         */
        if ($celda->getDataType() === DataType::TYPE_NUMERIC) {
            try {
                return Carbon::instance(
                    DateTime::createFromFormat('Y-m-d', $celda->getFormattedValue('yyyy-mm-dd'))
                )->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        $formatos = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y', 'd.m.Y', 'Y/m/d'];

        foreach ($formatos as $formato) {
            $fecha = DateTime::createFromFormat($formato, $valor);

            /*
             * createFromFormat no falla cuando la fecha no existe: da el
             * 31 de febrero sin quejarse, y con eso un dia malo pasaria por
             * bueno y se guardaria corrido al mes siguiente. Se mira el aviso
             * que deja, que es donde PHP dice que seSpecified una parte que
             * no existe.
             */
            $errores = DateTime::getLastErrors();

            if ($fecha === false) {
                continue;
            }

            $hayAviso = is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0);

            if (! $hayAviso) {
                return Carbon::instance($fecha)->toDateString();
            }
        }

        // Ultimo intento: que el texto sea una fecha que Carbon entienda.
        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * El valor de una celda como numero, o null si no lo es.
     *
     * Acepta el 1.234,56 que sale al copiar de una hoja de calculo con
     * separador de miles, y el 36,5824 con coma decimal. Sin esto, el numero
     * bueno se leeria como 1.23 y la compra saldria.convertida por un tercio.
     */
    private function aNumero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return is_finite((float) $valor) ? (float) $valor : null;
        }

        $texto = $this->textoDe($valor);

        if ($texto === '') {
            return null;
        }

        /*
         * Si la celda es de verdad un numero, se usa el valor interno y no el
         * texto. Excel guarda un numero con muchos decimales en notacion
         * cientifica cuando en la celda no cabe, y "3,66E+1" al normalizarlo
         * daria 3.66 en vez de 36.6: un tipo de cambio partido por diez. Con
         * el valor interno eso no pasa, porque el numero es el numero.
         */
        if (is_numeric(str_replace(',', '.', $texto)) && ! is_numeric($texto)) {
            $conPunto = str_replace(',', '.', $texto);

            if (is_numeric($conPunto)) {
                return (float) $conPunto;
            }
        }

        $texto = $this->normalizarNumero($texto);

        if ($texto === '') {
            return null;
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /**
     * Deja un numero escrito como lo entienda is_numeric.
     */
    private function normalizarNumero(string $texto): string
    {
        // Se quita lo que no sea numero, signo o separador
        $texto = preg_replace('/[^0-9,.\-+]/u', '', $texto) ?? '';

        if ($texto === '') {
            return '';
        }

        $comas = substr_count($texto, ',');
        $puntos = substr_count($texto, '.');

        if ($comas > 0 && $puntos > 0) {
            /*
             * Con los dos separadores, el que esta mas a la DERECHA es el
             * decimal y el otro va de miles. Es la unica regla que funciona en
             * los dos sentidos: en 1.234,56 el decimal es la coma, y en
             * 1,234.56 es el punto. Mirar cual de los dos caracteres "es el
             * decimal" segun el idioma no sirve, porque los dos son validos y
             * el archivo no dice de donde sale.
             */
            $decimalEsLaComa = strrpos($texto, ',') > strrpos($texto, '.');

            /*
             * El orden de los dos cambios importa y es el inverso del que
             * parece: primero se quita el separador de miles y despues se
             * convierte el decimal. Al reves, 1.234,56 pasa por 1.234.56 y
             * al quitar el punto queda 123456: mil veces el numero, y un
             * tipo de cambio de ciento veintitrés mil.
             */
            $texto = $decimalEsLaComa
                ? str_replace(',', '.', str_replace('.', '', $texto))
                : str_replace(',', '', $texto);
        } elseif ($comas === 1) {
            $texto = $this->comaDeMiles($texto) ? str_replace(',', '', $texto) : str_replace(',', '.', $texto);
        } elseif ($puntos === 1) {
            $texto = $this->puntoDeMiles($texto) ? str_replace('.', '', $texto) : $texto;
        } elseif ($puntos > 1) {
            $texto = str_replace('.', '', $texto);
        }

        return $texto;
    }

    /**
     * Si esa coma es el separador de miles y no el decimal.
     *
     * "1,234" son mil doscientos treinta y cuatro, no 1.234. Y lo que decide
     * la cosa es lo que hay detras: si son tres digitos, es miles, porque
     * detras de un decimal nunca hay tres digitos en un tipo de cambio.
     *
     * Sin esta regla, un 36,500 se leia como 36.5 y el total de todas las
     * compras del mes salia un 0.1% corto. Y 1,234 como 1.234 haria lo
     * contrario: treinta veces mas caro.
     */
    private function comaDeMiles(string $texto): bool
    {
        // 1,234 con lo que sea delante de la coma
        if (! preg_match('/^[+-]?\d{1,3},\d{3}$/', $texto)) {
            return false;
        }

        // Un tipo de cambio no llega a mil, asi que un millar con tres digitos
        // detras no es un tipo de cambio: es un numero de otra cosa.
        return (float) str_replace(',', '', $texto) >= 1000;
    }

    /**
     * Si ese punto es el separador de miles y no el decimal.
     *
     * "1.234" con tres digitos detras, al lado de una coma decimal en la
     * misma columna, son mil doscientos treinta y cuatro. Sin esto se leeria
     * como 1.234, que es mil veces mas.
     */
    private function puntoDeMiles(string $texto): bool
    {
        return (bool) preg_match('/^[+-]?\d{1,3}\.\d{3}$/', $texto);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /**
     * El texto de una celda, sea lo que sea.
     *
     * PhpSpreadsheet devuelve objetos raros cuando la celda tiene texto
     * enriquecido, que es lo que pasa si la fila se copio de una pagina web.
     * Sin esto, el encabezado llegaria aqui como un objeto y la comparacion
     * contra "fecha" no saldria nunca.
     */
    private function textoDe(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if ($valor instanceof RichText) {
            return $valor->getPlainText();
        }

        if (is_object($valor) && method_exists($valor, 'getPlainText')) {
            return (string) $valor->getPlainText();
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '';
        }

        return trim((string) $valor);
    }

    /**
     * Un texto tal cual, para poder compararlo con una lista de nombres.
     *
     * Se le quitan los acentos, los signos de puntuacion y los espacios, y se
     * pasa a minusculas. Asi "Fecha de operacion" y "FECHA_DE_OPERACION" son la
     * misma palabra, que es lo que hace falta cuando el archivo lo ha montado
     * una persona.
     */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($this->textoDe($texto), 'UTF-8');

        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e',
            'ë' => 'e', 'ê' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'ú' => 'u', 'ù' => 'u',
            'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]/u', '', $texto) ?? '';
    }

    /**
     * Una fecha como la ve el usuario, para poder nombrarla en un aviso.
     */
    private function mostrar(string $fecha): string
    {
        return Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/Y');
    }
}
