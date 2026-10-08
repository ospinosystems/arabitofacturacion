<?php

namespace App\Services\Cuadre;

/**
 * Lee y normaliza los archivos de "monto objetivo" del cuadre diario.
 *
 * Formato (ver database/data/FORMATO_CUADRE_DIARIO.md):
 *   FECHA, CONCEPTO (máquina fiscal), ..., FACTURA (inicio-fin o número), VENTA (Bs), TIPO
 *   TIPO ∈ { FISCAL RANGO, FISCAL UNITARIA, REDUCE EL TOTAL DE ESE DIA }.
 * También acepta el formato antiguo (MAQUINA_FISCAL, RANGO_FACTURA, TOTAL_VENTA).
 *
 * Soporta CSV/TSV y XLSX/XLS (vía PhpSpreadsheet). Puede fusionar varios archivos
 * (por ejemplo, uno por mes) en un solo CSV canónico para cuadre:pedidos-diario.
 */
class CuadreCsvReader
{
    public const TIPO_FISCAL_RANGO = 'FISCAL RANGO';
    public const TIPO_FISCAL_UNITARIA = 'FISCAL UNITARIA';
    public const TIPO_REDUCE_DIA = 'REDUCE EL TOTAL DE ESE DIA';

    /** Cabecera canónica del CSV fusionado. */
    public const CABECERA_CANONICA = ['FECHA', 'CONCEPTO', 'CEDULA', 'SERIE', 'NOTA DE CREDITO', 'AFECTADA', 'NUMERO DE Z', 'FACTURA', 'VENTA', 'TIPO'];

    protected int $scale = 4;

    /** @var array<int, string> razones de rechazo acumuladas en la última lectura (máx. 50) */
    protected array $razonesRechazo = [];

    public function razonesRechazo(): array
    {
        return $this->razonesRechazo;
    }

    /** @var array<int, string> avisos de la última lectura: correcciones automáticas y secciones omitidas (máx. 200) */
    protected array $avisos = [];

    public function avisos(): array
    {
        return $this->avisos;
    }

    /**
     * Lee un archivo (CSV/TSV/XLSX/XLS) y devuelve filas asociativas (cabecera → valor).
     */
    public function leerArchivo(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'xlsm', 'ods'], true)) {
            return $this->leerHojaCalculo($path);
        }
        return $this->leerCsv($path);
    }

    /**
     * Lee el CSV. Incluye columnas hasta "VENTA" o "TOTAL VENTA" y hasta "TIPO" si existe.
     */
    public function leerCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        // Normaliza saltos de línea Windows/Mac.
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $delim = $this->detectarDelimitador($path, $content);
        $lines = array_map('trim', explode("\n", $content));

        $primeraLinea = '';
        while (!empty($lines) && $primeraLinea === '') {
            $primeraLinea = array_shift($lines);
        }
        if ($primeraLinea === '') {
            return [];
        }
        $header = str_getcsv($primeraLinea, $delim);
        $header = array_map(function ($h) {
            $h = trim((string) $h);
            return preg_replace('/^\xEF\xBB\xBF/', '', $h);
        }, $header);

        $lastIndex = $this->ultimoIndiceUtil($header);
        $header = array_slice($header, 0, $lastIndex + 1);

        $out = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $row = str_getcsv($line, $delim);
            $row = array_pad($row, count($header), '');
            $row = array_slice($row, 0, $lastIndex + 1);
            $assoc = @array_combine($header, $row);
            if ($assoc !== false) {
                $out[] = $assoc;
            }
        }
        return $out;
    }

    /**
     * Lee la primera hoja con datos de un XLSX/XLS y la convierte a filas asociativas
     * con los mismos criterios que el CSV (fechas Excel → YYYY-MM-DD, fórmulas evaluadas).
     *
     * Reconoce dos diseños:
     *  - "Libro de ventas" mensual (SENIAT): cabecera de dos filas (FECHA, CLIENTE, …, Nº Z, Nº FACTURA, TOTAL VENTA)
     *    en cualquier fila, una fila "RESUMEN DE VENTAS <máquina>" por día y máquina, notas de crédito y facturas
     *    manuales (SERIE R). Se convierte al contrato canónico (FECHA, CONCEPTO, FACTURA, VENTA, TIPO).
     *  - Tabla plana con cabecera FECHA/CONCEPTO/FACTURA/VENTA/TIPO (la cabecera puede no estar en la fila 1).
     */
    public function leerHojaCalculo(string $path): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('PhpSpreadsheet no está instalado; no se puede leer ' . basename($path));
        }
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        // No usar setReadDataOnly(true): se perderían los formatos y no se podría detectar qué celdas son fechas.
        $spreadsheet = $reader->load($path);

        $out = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $matriz = $this->leerMatriz($sheet);
            if (count($matriz) < 2) {
                continue;
            }

            $libro = $this->leerLibroVentas($matriz, basename($path));
            if ($libro !== null) {
                $out = $libro;
                break;
            }

            // Formato plano: la cabecera es la primera fila con FECHA y alguna columna de monto/máquina.
            $filaCabecera = null;
            foreach ($matriz as $r => $fila) {
                $u = mb_strtoupper(implode(' ', $fila));
                if (strpos($u, 'FECHA') !== false && preg_match('/VENTA|CONCEPTO|MAQUINA|MÁQUINA|ZZN\d+/u', $u)) {
                    $filaCabecera = $r;
                    break;
                }
            }
            if ($filaCabecera === null) {
                $filaCabecera = array_key_first($matriz);
            }
            $header = array_map('trim', $matriz[$filaCabecera]);
            $lastIndex = $this->ultimoIndiceUtil($header);
            $header = array_slice($header, 0, $lastIndex + 1);
            foreach ($matriz as $r => $fila) {
                if ($r <= $filaCabecera) {
                    continue;
                }
                $row = array_pad($fila, count($header), '');
                $row = array_slice($row, 0, $lastIndex + 1);
                $assoc = @array_combine($header, $row);
                if ($assoc !== false) {
                    $assoc['_fila'] = (string) $r;
                    $out[] = $assoc;
                }
            }
            // Solo la primera hoja con datos.
            break;
        }
        $spreadsheet->disconnectWorksheets();
        return $out;
    }

    /**
     * Devuelve la hoja como matriz [númeroFila => [valores...]] (índice 0 = columna A), omitiendo filas vacías.
     * Las fórmulas se evalúan, las fechas se devuelven como YYYY-MM-DD y el resto como texto recortado.
     */
    protected function leerMatriz(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($r = 1; $r <= $highestRow; $r++) {
            $fila = [];
            $vacia = true;
            for ($c = 1; $c <= $highestCol; $c++) {
                $cell = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r);
                $val = $cell->getValue();
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                } elseif (is_string($val) && $val !== '' && $val[0] === '=') {
                    try {
                        $val = $cell->getCalculatedValue();
                    } catch (\Throwable $e) {
                        $val = '';
                    }
                }
                if ($val !== null && $val !== '' && is_numeric($val) && (float) $val >= 1 && (float) $val < 2958466
                    && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                    $val = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)->format('Y-m-d');
                }
                $s = is_scalar($val) ? trim((string) $val) : '';
                if ($s !== '') {
                    $vacia = false;
                }
                $fila[] = $s;
            }
            if (!$vacia) {
                $rows[$r] = $fila;
            }
        }
        return $rows;
    }

    /**
     * Interpreta un "libro de ventas" mensual y lo convierte a filas canónicas
     * (FECHA, CONCEPTO, CEDULA, SERIE, NOTA DE CREDITO, AFECTADA, NUMERO DE Z, FACTURA, VENTA, TIPO).
     *
     * Reglas:
     *  - "RESUMEN DE VENTAS <máquina>" con Nº FACTURA "inicio-fin" → FISCAL RANGO (VENTA = TOTAL VENTA).
     *  - "RESUMEN DE VENTAS <máquina>" con Nº NOTA DE CREDITO → REDUCE EL TOTAL DE ESE DIA (monto negativo, esa máquina).
     *  - Fila de cliente con "SERIE R 000123" (factura manual) → FISCAL UNITARIA, CONCEPTO = "SERIE R", FACTURA = 123.
     *  - Fila de cliente con nota de crédito → REDUCE; la máquina se infiere del número de factura afectada.
     *  - Se omiten: ANULADA, Z sin ventas (factura "0" y total 0), filas solo de retención (sin total), VAN/VIENEN/TOTALES,
     *    y las secciones tituladas con otra "SUCURSAL …" distinta de la primera del libro.
     *
     * Correcciones automáticas (cada una deja un aviso en avisos()):
     *  - Año mal digitado: filas cuyo mes coincide con el mes predominante del libro pero el año difiere.
     *  - Rangos mal digitados: la numeración de cada máquina es continua (la primera factura de un día sigue a la
     *    última del anterior); los extremos que rompen la continuidad se reconstruyen a partir de los vecinos.
     *
     * @param array<int, array<int, string>> $matriz
     * @return array<int, array<string, string>>|null null si la hoja no tiene la cabecera de libro de ventas.
     */
    public function leerLibroVentas(array $matriz, string $origen = ''): ?array
    {
        $numerosFila = array_keys($matriz);
        $mapa = null;
        $parsed = [];
        $seccion = '';
        $seccionPrincipal = null;
        $omitidasPorSeccion = [];

        foreach ($numerosFila as $pos => $r) {
            $fila = $matriz[$r];
            $u = mb_strtoupper(implode(' ', $fila));
            if (strpos($u, 'FECHA') !== false && strpos($u, 'CLIENTE') !== false) {
                $siguiente = $numerosFila[$pos + 1] ?? null;
                $fila2 = ($siguiente !== null && $siguiente === $r + 1) ? $matriz[$siguiente] : [];
                $nuevoMapa = $this->mapearCabeceraLibro($fila, $fila2);
                if ($nuevoMapa !== null) {
                    $nuevoMapa['_fila2'] = $fila2 !== [] ? $siguiente : null;
                    $mapa = $nuevoMapa;
                }
                continue;
            }
            if ($mapa === null || (isset($mapa['_fila2']) && $r === $mapa['_fila2'])) {
                // Título de sección ("SUCURSAL ANACO, …") antes de la primera cabecera.
                $this->detectarSeccion($fila, $seccion);
                continue;
            }

            $fecha = $this->normalizarFecha((string) ($fila[$mapa['fecha']] ?? ''));
            if ($fecha === '') {
                $this->detectarSeccion($fila, $seccion); // VAN…, …VIENEN, TOTALES, títulos de página
                continue;
            }
            if ($seccionPrincipal === null) {
                $seccionPrincipal = $seccion;
            }
            if ($seccion !== $seccionPrincipal) {
                $omitidasPorSeccion[$seccion] = ($omitidasPorSeccion[$seccion] ?? 0) + 1;
                continue;
            }

            $cliente = trim((string) ($fila[$mapa['cliente']] ?? ''));
            $total = $this->totalFilaLibro($fila, $mapa);
            $nc = trim((string) ($fila[$mapa['nc']] ?? ''));
            $factura = trim((string) ($fila[$mapa['factura']] ?? ''));
            $p = [
                'fila' => $r, 'fecha' => $fecha, 'cliente' => $cliente, 'total' => $total, 'nc' => $nc,
                'afectada' => trim((string) ($fila[$mapa['afectada']] ?? '')),
                'serie' => trim((string) ($fila[$mapa['serie']] ?? '')),
                'z' => trim((string) ($fila[$mapa['z']] ?? '')),
                'factura' => $factura,
                'cedula' => trim((string) ($fila[$mapa['cedula']] ?? '')),
                'maquina' => '', 'rango' => null, 'rango_original' => null,
            ];
            if (preg_match('/RESUMEN\s+DE\s+VENTAS?\s*[:\-]?\s*([A-Z0-9\-]+)/iu', $cliente, $m)) {
                $p['maquina'] = mb_strtoupper($m[1]);
            } elseif (preg_match('/^RESUMEN\s+DE\s+VENTAS?\s*$/iu', $cliente) && ($mapa['maquina_cabecera'] ?? '') !== '') {
                $p['maquina'] = $mapa['maquina_cabecera'];
            }
            if ($p['maquina'] !== '' && $nc === '' && preg_match('/^(\d+)\s*[-–]\s*(\d+)$/u', $factura, $mm)) {
                $p['rango'] = [(int) $mm[1], (int) $mm[2]];
                $p['rango_original'] = $p['rango'];
            }
            $parsed[] = $p;
        }

        if ($mapa === null) {
            return null;
        }
        foreach ($omitidasPorSeccion as $nombre => $cuantas) {
            $this->avisar($origen, 0, sprintf('sección "%s" omitida (%d filas): no es la sucursal del libro ("%s")', $nombre, $cuantas, $seccionPrincipal));
        }

        $this->corregirAnioPorMesPredominante($parsed, $origen);
        $this->corregirRangosPorContinuidad($parsed, $origen);

        $rangosPorMaquina = [];
        foreach ($parsed as $p) {
            if ($p['rango'] !== null) {
                $rangosPorMaquina[$p['maquina']][] = $p['rango'];
            }
        }

        $out = [];
        foreach ($parsed as $p) {
            $canon = [
                'FECHA' => $p['fecha'], 'CONCEPTO' => '', 'CEDULA' => $p['cedula'], 'SERIE' => $p['serie'],
                'NOTA DE CREDITO' => $p['nc'], 'AFECTADA' => $p['afectada'], 'NUMERO DE Z' => $p['z'],
                'FACTURA' => '', 'VENTA' => '', 'TIPO' => '', '_fila' => (string) $p['fila'],
            ];
            $total = $p['total'];
            $esCero = $total === null || bccomp($total, '0', $this->scale) === 0;
            $clienteU = mb_strtoupper($p['cliente']);

            if ($p['maquina'] !== '') {
                $canon['CONCEPTO'] = $p['maquina'];
                if ($p['nc'] !== '') {
                    if ($esCero) {
                        continue;
                    }
                    $canon['VENTA'] = $this->negativo($total);
                    $canon['TIPO'] = self::TIPO_REDUCE_DIA;
                } elseif ($p['rango'] !== null) {
                    if ($esCero) {
                        continue; // Z sin ventas
                    }
                    $canon['FACTURA'] = min($p['rango']) . '-' . max($p['rango']);
                    $canon['VENTA'] = $total;
                    $canon['TIPO'] = self::TIPO_FISCAL_RANGO;
                } elseif (preg_match('/^\d+$/', $p['factura']) && (int) $p['factura'] > 0) {
                    if ($esCero) {
                        continue;
                    }
                    $canon['FACTURA'] = (string) (int) $p['factura'];
                    $canon['VENTA'] = $total;
                    $canon['TIPO'] = self::TIPO_FISCAL_UNITARIA;
                } else {
                    if ($esCero) {
                        continue; // Z sin ventas (factura "0" o vacía)
                    }
                    $this->rechazar($origen, $p['fila'], 'RESUMEN DE VENTAS con monto pero sin rango de facturas (factura="' . substr($p['factura'], 0, 30) . '", total=' . $total . ')');
                    continue;
                }
                $out[] = $canon;
                continue;
            }

            // Fila de cliente (factura manual, nota de crédito o solo retención).
            if ($clienteU === 'ANULADA' || $clienteU === 'ANULADO') {
                continue;
            }
            if ($p['nc'] !== '') {
                if ($esCero) {
                    continue;
                }
                $canon['CONCEPTO'] = $this->maquinaDeFacturaAfectada($p['afectada'], $rangosPorMaquina);
                $canon['VENTA'] = $this->negativo($total);
                $canon['TIPO'] = self::TIPO_REDUCE_DIA;
                $out[] = $canon;
                continue;
            }
            if ($esCero) {
                continue; // solo comprobante de retención, sin venta
            }
            if ($p['serie'] !== '' && preg_match('/^(.*?)\s*0*(\d+)\s*$/u', $p['serie'], $m)) {
                $concepto = mb_strtoupper(trim($m[1]));
                $canon['CONCEPTO'] = $concepto !== '' ? $concepto : 'SERIE';
                if (bccomp($total, '0', $this->scale) < 0) {
                    $canon['VENTA'] = $total;
                    $canon['TIPO'] = self::TIPO_REDUCE_DIA;
                } else {
                    $canon['FACTURA'] = $m[2];
                    $canon['VENTA'] = $total;
                    $canon['TIPO'] = self::TIPO_FISCAL_UNITARIA;
                }
                $out[] = $canon;
                continue;
            }
            $this->rechazar($origen, $p['fila'], 'fila de cliente con monto pero sin serie ni nota de crédito (cliente="' . substr($p['cliente'], 0, 30) . '", total=' . $total . ')');
        }

        return $out;
    }

    /** Si la fila es un título "SUCURSAL …", actualiza la sección actual. */
    protected function detectarSeccion(array $fila, string &$seccion): void
    {
        foreach ($fila as $celda) {
            $c = trim((string) $celda);
            if ($c !== '' && preg_match('/^SUCURSAL\b/iu', $c)) {
                $seccion = mb_strtoupper(preg_replace('/\s+/', ' ', $c));
                return;
            }
        }
    }

    /**
     * Corrige el año de las filas cuyo mes coincide con el mes predominante del libro pero el año no
     * (error típico al digitar: "08/01/2025" en el libro de enero 2026).
     */
    protected function corregirAnioPorMesPredominante(array &$parsed, string $origen): void
    {
        $meses = [];
        foreach ($parsed as $p) {
            if ($p['maquina'] !== '') {
                $ym = substr($p['fecha'], 0, 7);
                $meses[$ym] = ($meses[$ym] ?? 0) + 1;
            }
        }
        if (count($meses) < 2) {
            return;
        }
        arsort($meses);
        $dominante = (string) array_key_first($meses);
        [$anio, $mes] = explode('-', $dominante);
        foreach ($parsed as $i => $p) {
            if (substr($p['fecha'], 5, 2) === $mes && substr($p['fecha'], 0, 4) !== $anio) {
                $nueva = $anio . substr($p['fecha'], 4);
                if (checkdate((int) $mes, (int) substr($nueva, 8, 2), (int) $anio)) {
                    $this->avisar($origen, $p['fila'], sprintf('fecha %s corregida a %s (el libro es de %s)', $p['fecha'], $nueva, $dominante));
                    $parsed[$i]['fecha'] = $nueva;
                }
            }
        }
    }

    /**
     * Repara los rangos "inicio-fin" mal digitados usando la continuidad de la numeración de cada máquina:
     * dentro de un libro, la primera factura de un día es la siguiente a la última del día anterior.
     *
     * Cada frontera entre dos filas consecutivas tiene dos observaciones (fin de una, inicio-1 de la otra). Se busca
     * la cadena creciente más larga de fronteras con saltos plausibles; las fronteras fuera de la cadena se
     * reconstruyen con candidatos (un dígito cambiado/transpuesto en lo observado, o los extremos que otra máquina
     * tiene el mismo día, típicos de un intercambio de celdas) acotados por las fronteras confiables vecinas; si no
     * hay candidato, se reparte proporcionalmente al monto vendido.
     */
    protected function corregirRangosPorContinuidad(array &$parsed, string $origen): void
    {
        $porMaquina = [];
        foreach ($parsed as $i => $p) {
            if ($p['rango'] !== null) {
                $porMaquina[$p['maquina']][] = $i;
            }
        }
        foreach ($porMaquina as $maquina => $indices) {
            usort($indices, fn ($a, $b) => [$parsed[$a]['fecha'], $parsed[$a]['fila']] <=> [$parsed[$b]['fecha'], $parsed[$b]['fila']]);
            $n = count($indices);
            if ($n < 2) {
                continue;
            }
            // Fronteras: v[0] = inicio de la 1ª fila − 1; v[k] = fin de la fila k (= inicio de la k+1 − 1); v[n] = fin de la última.
            $cands = [0 => [$parsed[$indices[0]]['rango'][0] - 1]];
            for ($k = 1; $k < $n; $k++) {
                $cands[$k] = array_values(array_unique([$parsed[$indices[$k - 1]]['rango'][1], $parsed[$indices[$k]]['rango'][0] - 1]));
            }
            $cands[$n] = [$parsed[$indices[$n - 1]]['rango'][1]];

            $cuentas = [];
            foreach ($indices as $idx) {
                [$a, $b] = $parsed[$idx]['rango'];
                if ($b >= $a && $b - $a + 1 <= 3000) {
                    $cuentas[] = $b - $a + 1;
                }
            }
            sort($cuentas);
            $med = $cuentas ? $cuentas[intdiv(count($cuentas), 2)] : 200;
            $maxSalto = max(1500, 6 * $med);

            // Cadena creciente más larga eligiendo a lo sumo un candidato por frontera.
            $best = [];
            $prev = [];
            for ($i = 0; $i <= $n; $i++) {
                foreach ($cands[$i] as $ci => $v) {
                    $best[$i][$ci] = 1;
                    $prev[$i][$ci] = null;
                    for ($j = 0; $j < $i; $j++) {
                        foreach ($cands[$j] as $cj => $w) {
                            $salto = $v - $w;
                            if ($salto >= ($i - $j) && $salto <= $maxSalto * ($i - $j) && $best[$j][$cj] + 1 > $best[$i][$ci]) {
                                $best[$i][$ci] = $best[$j][$cj] + 1;
                                $prev[$i][$ci] = [$j, $cj];
                            }
                        }
                    }
                }
            }
            $mejor = null;
            $mejorLen = 0;
            for ($i = 0; $i <= $n; $i++) {
                foreach ($cands[$i] as $ci => $v) {
                    if ($best[$i][$ci] > $mejorLen) {
                        $mejorLen = $best[$i][$ci];
                        $mejor = [$i, $ci];
                    }
                }
            }
            $fijos = [];
            for ($cur = $mejor; $cur !== null; $cur = $prev[$cur[0]][$cur[1]]) {
                $fijos[$cur[0]] = $cands[$cur[0]][$cur[1]];
            }
            // Aunque la cadena cubra todas las fronteras, puede haber elegido "inicio − 1" donde el "fin" de la fila anterior
            // discrepaba (solape de un día con el siguiente): la reescritura final corrige esa fila.

            $valores = $fijos;
            for ($i = 0; $i <= $n; $i++) {
                if (isset($valores[$i])) {
                    continue;
                }
                $lo = null;
                $loPos = null;
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (isset($valores[$j])) {
                        $lo = $valores[$j];
                        $loPos = $j;
                        break;
                    }
                }
                $hi = null;
                $hiPos = null;
                for ($j = $i + 1; $j <= $n; $j++) {
                    if (isset($fijos[$j])) {
                        $hi = $fijos[$j];
                        $hiPos = $j;
                        break;
                    }
                }
                $min = $lo !== null ? $lo + ($i - $loPos) : null;
                $max = $hi !== null ? $hi - ($hiPos - $i) : null;

                $candidatos = [];
                foreach ($cands[$i] as $v) {
                    foreach ($this->edicionesDeUnDigito($v) as $e) {
                        $candidatos[] = $e;
                    }
                }
                if ($i >= 1) {
                    $fechaFila = $parsed[$indices[$i - 1]]['fecha'];
                    foreach ($parsed as $q) {
                        if ($q['rango_original'] !== null && $q['maquina'] !== $maquina && $q['fecha'] === $fechaFila) {
                            $candidatos[] = $q['rango_original'][1];
                            $candidatos[] = $q['rango_original'][0] - 1;
                        }
                    }
                }
                $candidatos = array_values(array_unique(array_filter($candidatos, function ($c) use ($min, $max) {
                    return ($min === null || $c >= $min) && ($max === null || $c <= $max);
                })));

                if ($lo !== null && $hi !== null) {
                    $sumHasta = 0.0;
                    $sumTotal = 0.0;
                    for ($k = $loPos; $k < $hiPos; $k++) {
                        $m = max(0.0, (float) $parsed[$indices[$k]]['total']);
                        $sumTotal += $m;
                        if ($k < $i) {
                            $sumHasta += $m;
                        }
                    }
                    $ref = $sumTotal > 0 ? (int) round($lo + ($hi - $lo) * $sumHasta / $sumTotal) : (int) round($lo + ($hi - $lo) * ($i - $loPos) / ($hiPos - $loPos));
                    $ref = max($min, min($max, $ref));
                } elseif ($lo !== null) {
                    $ref = $lo + $med;
                } else {
                    $ref = $hi - $med;
                }
                if ($candidatos) {
                    usort($candidatos, fn ($a, $b) => abs($a - $ref) <=> abs($b - $ref));
                    $valores[$i] = $candidatos[0];
                } else {
                    $valores[$i] = $ref;
                }
            }

            for ($k = 0; $k < $n; $k++) {
                $idx = $indices[$k];
                $nuevo = [$valores[$k] + 1, $valores[$k + 1]];
                if ($nuevo !== $parsed[$idx]['rango']) {
                    $this->avisar($origen, $parsed[$idx]['fila'], sprintf('%s rango %d-%d corregido a %d-%d por continuidad de la numeración de %s', $parsed[$idx]['fecha'], $parsed[$idx]['rango'][0], $parsed[$idx]['rango'][1], $nuevo[0], $nuevo[1], $maquina));
                    $parsed[$idx]['rango'] = $nuevo;
                }
            }
        }
    }

    /** Variantes de un número con un dígito sustituido o dos adyacentes transpuestos (errores de digitación). */
    protected function edicionesDeUnDigito(int $v): array
    {
        $s = (string) max(0, $v);
        $len = strlen($s);
        $out = [];
        for ($i = 0; $i < $len; $i++) {
            for ($d = 0; $d <= 9; $d++) {
                if ((string) $d === $s[$i] || ($i === 0 && $d === 0 && $len > 1)) {
                    continue;
                }
                $t = $s;
                $t[$i] = (string) $d;
                $out[] = (int) $t;
            }
            if ($i + 1 < $len && $s[$i] !== $s[$i + 1]) {
                $t = $s;
                $t[$i] = $s[$i + 1];
                $t[$i + 1] = $s[$i];
                if ($t[0] !== '0') {
                    $out[] = (int) $t;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Mapea la cabecera de dos filas del libro de ventas a índices de columna.
     * @return array<string, int|null>|null null si faltan FECHA, CLIENTE, Nº FACTURA o TOTAL.
     */
    protected function mapearCabeceraLibro(array $fila1, array $fila2): ?array
    {
        $n = max(count($fila1), count($fila2));
        $mapa = ['fecha' => null, 'cliente' => null, 'cedula' => null, 'serie' => null, 'nc' => null, 'afectada' => null,
            'z' => null, 'factura' => null, 'total' => null, 'componentes' => [], 'maquina_cabecera' => ''];
        for ($c = 0; $c < $n; $c++) {
            $t = trim(($fila1[$c] ?? '') . ' ' . ($fila2[$c] ?? ''));
            if ($t === '') {
                continue;
            }
            $u = mb_strtoupper($t);
            $u = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $u);
            $norm = trim(preg_replace('/\s+/', ' ', preg_replace('/[º°.]/u', '', $u)));

            if ($mapa['fecha'] === null && strpos($norm, 'FECHA') === 0 && strpos($norm, 'COMPROBANTE') === false) {
                $mapa['fecha'] = $c;
            } elseif ($mapa['cliente'] === null && strpos($norm, 'CLIENTE') !== false) {
                $mapa['cliente'] = $c;
            } elseif ($mapa['cedula'] === null && (strpos($norm, 'CI/RIF') !== false || strpos($norm, 'C I') !== false || strpos($norm, 'RIF') !== false || strpos($norm, 'CEDULA') !== false)) {
                $mapa['cedula'] = $c;
            } elseif ($mapa['serie'] === null && (strpos($norm, 'SERIE') !== false || $norm === 'FACTURA')) {
                // "FACTURA" a secas (sin Nº) es la columna de las facturas manuales (SERIE R, SERIE K…); la del rango es "Nº FACTURA".
                $mapa['serie'] = $c;
            } elseif ($mapa['nc'] === null && (strpos($norm, 'CREDITO') !== false || (strpos($norm, 'NOTA') !== false && strpos($norm, 'DEBITO') === false))) {
                // La de nota de DÉBITO (algunos libros la ponen antes) no es nota de crédito.
                $mapa['nc'] = $c;
            } elseif ($mapa['afectada'] === null && strpos($norm, 'AFECTADA') !== false) {
                $mapa['afectada'] = $c;
            } elseif ($mapa['z'] === null && preg_match('/(^|\s)N\s*Z(\s|$)/', $norm)) {
                $mapa['z'] = $c;
                // Algunos libros ponen el serial de la máquina en la fila superior de la cabecera, sobre "Nº Z".
                $encima = mb_strtoupper(trim((string) ($fila1[$c] ?? '')));
                if (preg_match('/^[A-Z]{1,4}[A-Z0-9]{5,}$/', $encima)) {
                    $mapa['maquina_cabecera'] = $encima;
                }
            } elseif ($mapa['factura'] === null && preg_match('/(^|\s)N\s*(DE )?FACTURA(\s|$)/', $norm) && strpos($norm, 'SERIE') === false) {
                $mapa['factura'] = $c;
            } elseif ($mapa['total'] === null && strpos($norm, 'TOTAL') !== false && strpos($norm, 'VENTA') !== false) {
                $mapa['total'] = $c;
            } elseif ($mapa['total'] !== null && strpos($norm, 'RET') === false
                && (strpos($norm, 'EXENTA') !== false || preg_match('/(^|\s)BASE(\s|$)/', $norm) || preg_match('/(^|\s)IVA(\s|$)/', $norm))) {
                $mapa['componentes'][] = $c;
            }
        }
        if ($mapa['fecha'] === null || $mapa['cliente'] === null || $mapa['factura'] === null || ($mapa['total'] === null && empty($mapa['componentes']))) {
            return null;
        }
        return $mapa;
    }

    /** TOTAL VENTA de la fila; si la celda no es numérica, suma exentas + base + IVA. */
    protected function totalFilaLibro(array $fila, array $mapa): ?string
    {
        if ($mapa['total'] !== null) {
            $t = $this->normalizarMonto((string) ($fila[$mapa['total']] ?? ''));
            if ($t !== null) {
                return $t;
            }
        }
        $suma = null;
        foreach ($mapa['componentes'] as $c) {
            $v = $this->normalizarMonto((string) ($fila[$c] ?? ''));
            if ($v !== null) {
                $suma = bcadd($suma ?? '0', $v, $this->scale);
            }
        }
        return $suma;
    }

    /** Máquina cuyo rango de facturas (en el mismo libro) contiene el número afectado; '' si no se determina. */
    protected function maquinaDeFacturaAfectada(string $afectada, array $rangosPorMaquina): string
    {
        if (preg_match('/^(.*?)\s*0*(\d+)\s*$/u', $afectada, $m) && trim($m[1]) !== '' && !preg_match('/^\d+$/', trim($m[1]))) {
            return mb_strtoupper(trim($m[1])); // p. ej. "SERIE R 00000150" → SERIE R
        }
        $num = (int) preg_replace('/\D/', '', $afectada);
        if ($num <= 0) {
            return '';
        }
        foreach ($rangosPorMaquina as $maquina => $rangos) {
            foreach ($rangos as [$a, $b]) {
                if ($num >= $a && $num <= $b) {
                    return (string) $maquina;
                }
            }
        }
        return '';
    }

    protected function negativo(string $monto): string
    {
        return bccomp($monto, '0', $this->scale) > 0 ? bcmul($monto, '-1', $this->scale) : $monto;
    }

    protected function rechazar(string $origen, int $fila, string $razon): void
    {
        if (count($this->razonesRechazo) < 50) {
            $this->razonesRechazo[] = sprintf('%s fila %d: %s', $origen, $fila, $razon);
        }
    }

    protected function avisar(string $origen, int $fila, string $aviso): void
    {
        if (count($this->avisos) < 200) {
            $this->avisos[] = $fila > 0 ? sprintf('%s fila %d: %s', $origen, $fila, $aviso) : sprintf('%s: %s', $origen, $aviso);
        }
    }

    protected function detectarDelimitador(string $path, string $content): string
    {
        if (strpos($path, '.tsv') !== false) {
            return "\t";
        }
        $primera = strtok($content, "\n") ?: '';
        $coma = substr_count($primera, ',');
        $pc = substr_count($primera, ';');
        $tab = substr_count($primera, "\t");
        if ($tab > $coma && $tab > $pc) {
            return "\t";
        }
        if ($pc > $coma) {
            return ';';
        }
        return ',';
    }

    /** Índice de la última columna útil: hasta VENTA/TOTAL VENTA o TIPO (lo demás se ignora). */
    protected function ultimoIndiceUtil(array $header): int
    {
        $lastIndex = -1;
        foreach ($header as $i => $h) {
            $u = mb_strtoupper((string) $h);
            if (stripos($u, 'VENTA') !== false || stripos($u, 'TIPO') !== false) {
                $lastIndex = max($lastIndex, $i);
            }
        }
        if ($lastIndex < 0) {
            $lastIndex = count($header) - 1;
        }
        return $lastIndex;
    }

    /**
     * Detecta columnas: FECHA, CONCEPTO (máquina), FACTURA, VENTA, TIPO.
     * Acepta también formato antiguo: MAQUINA_FISCAL, RANGO_FACTURA, TOTAL_VENTA.
     */
    public function detectarColumnas(array $headerKeys): array
    {
        $headerKeys = array_values($headerKeys);
        $map = ['fecha' => null, 'maquina' => null, 'rango' => null, 'total_venta' => null, 'tipo' => null];

        foreach ($headerKeys as $key) {
            $k = trim((string) $key);
            $u = mb_strtoupper($k);
            $uNorm = preg_replace('/\s+/', ' ', $u);

            if (stripos($u, 'FECHA') !== false && $map['fecha'] === null) {
                $map['fecha'] = $key;
            }
            if (stripos($u, 'CONCEPTO') !== false) {
                $map['maquina'] = $key;
            }
            if (stripos($u, 'TOTAL') !== false && stripos($u, 'VENTA') !== false) {
                $map['total_venta'] = $key;
            }
            if (stripos($u, 'VENTA') !== false && stripos($u, 'TOTAL') === false && $map['total_venta'] === null) {
                $map['total_venta'] = $key;
            }
            if (stripos($u, 'TIPO') !== false) {
                $map['tipo'] = $key;
            }
            if ((stripos($u, 'MAQUINA') !== false || stripos($u, 'MÁQUINA') !== false) && stripos($u, 'FISCAL') !== false && $map['maquina'] === null) {
                $map['maquina'] = $key;
            }
            if (stripos($u, 'FACTURA') !== false && (stripos($u, 'RANGO') !== false || stripos($u, 'N°') !== false || stripos($u, 'NUMERO') !== false || preg_match('/N[\s.]*FACTURA/', $uNorm) || $u === 'FACTURA')) {
                $map['rango'] = $key;
            }
            if (preg_match('/^ZZN\d+$/i', $k) && $map['maquina'] === null) {
                $map['maquina'] = $key;
                if ($map['total_venta'] === null) {
                    $map['total_venta'] = $key;
                }
            }
        }
        return $map;
    }

    /**
     * Convierte fecha a YYYY-MM-DD. Acepta: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY, D/M/YYYY, YYYY/MM/DD,
     * y "YYYY-MM-DD HH:MM:SS". Devuelve '' si no reconoce el formato.
     */
    public function normalizarFecha(string $fecha): string
    {
        $f = trim($fecha);
        if ($f === '') {
            return '';
        }
        // Quita hora si viene.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]\d{1,2}:\d{2}/', $f, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            return $f;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $f, $m)) {
            $d = str_pad((string) (int) $m[1], 2, '0', STR_PAD_LEFT);
            $mes = str_pad((string) (int) $m[2], 2, '0', STR_PAD_LEFT);
            return $m[3] . '-' . $mes . '-' . $d;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2})$/', $f, $m)) {
            $d = str_pad((string) (int) $m[1], 2, '0', STR_PAD_LEFT);
            $mes = str_pad((string) (int) $m[2], 2, '0', STR_PAD_LEFT);
            return '20' . $m[3] . '-' . $mes . '-' . $d;
        }
        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $f, $m)) {
            return $m[1] . '-' . str_pad((string) (int) $m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) (int) $m[3], 2, '0', STR_PAD_LEFT);
        }
        // Serial de fecha de Excel (días desde 1899-12-30): 36526 = 2000-01-01, 54789 = 2050-01-01.
        if (preg_match('/^\d{5}(\.0+)?$/', $f)) {
            $serial = (int) $f;
            if ($serial >= 36526 && $serial <= 54789) {
                return date('Y-m-d', (int) (($serial - 25569) * 86400));
            }
        }
        return '';
    }

    /**
     * Normaliza un monto: quita espacios y separadores de miles; acepta "1.234,56", "1,234.56", "1234.56", "1234,56".
     * Devuelve string numérico con punto decimal o null si no es numérico.
     */
    public function normalizarMonto(string $valor): ?string
    {
        $v = preg_replace('/[\s\x{00A0}]+/u', '', trim($valor));
        if ($v === '' || $v === null) {
            return null;
        }
        $v = str_replace(['Bs.', 'Bs', 'BS', '$'], '', $v);
        $tieneComa = strpos($v, ',') !== false;
        $tienePunto = strpos($v, '.') !== false;
        if ($tieneComa && $tienePunto) {
            // El último separador es el decimal.
            if (strrpos($v, ',') > strrpos($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                $v = str_replace(',', '', $v);
            }
        } elseif ($tieneComa) {
            // Una sola coma: decimal si tiene 1-2 dígitos detrás o es la única; si hay varias comas, son miles.
            if (substr_count($v, ',') > 1) {
                $v = str_replace(',', '', $v);
            } else {
                $v = str_replace(',', '.', $v);
            }
        } elseif ($tienePunto && substr_count($v, '.') > 1) {
            $v = str_replace('.', '', $v);
        }
        if (!is_numeric($v)) {
            return null;
        }
        return $v;
    }

    /**
     * Normaliza una fila usando el mapa de columnas detectado.
     * @param string|null $razonRechazo Se rellena cuando se devuelve null.
     */
    public function normalizarFila(array $fila, array $columnMap, ?string &$razonRechazo = null): ?array
    {
        $razonRechazo = null;
        $totalVentaKey = $columnMap['total_venta'] ?? null;
        $fechaKey = $columnMap['fecha'] ?? null;
        $maquinaKey = $columnMap['maquina'] ?? null;
        $rangoKey = $columnMap['rango'] ?? null;
        $tipoKey = $columnMap['tipo'] ?? null;

        if ($totalVentaKey === null || $fechaKey === null) {
            $razonRechazo = 'falta columna total_venta o fecha en CSV (map: total_venta=' . ($totalVentaKey ?? 'null') . ', fecha=' . ($fechaKey ?? 'null') . ')';
            return null;
        }

        $totalVenta = $this->normalizarMonto((string) ($fila[$totalVentaKey] ?? ''));
        if ($totalVenta === null) {
            $razonRechazo = 'total_venta vacío o no numérico (valor="' . substr((string) ($fila[$totalVentaKey] ?? ''), 0, 30) . '")';
            return null;
        }

        $fecha = trim((string) ($fila[$fechaKey] ?? ''));
        if ($fecha === '') {
            $razonRechazo = 'fecha vacía';
            return null;
        }
        $fechaOriginal = $fecha;
        $fecha = $this->normalizarFecha($fecha);
        if ($fecha === '') {
            $razonRechazo = 'fecha no reconocible (original="' . $fechaOriginal . '")';
            return null;
        }

        $maquina = $maquinaKey !== null ? trim((string) ($fila[$maquinaKey] ?? '')) : '';
        if ($maquinaKey !== null && $maquina === '' && preg_match('/^ZZN\d+$/i', (string) $maquinaKey)) {
            $maquina = (string) $maquinaKey;
        }

        $factura = $rangoKey !== null ? trim((string) ($fila[$rangoKey] ?? '')) : '';
        $tipoRaw = $tipoKey !== null ? trim(mb_strtoupper((string) ($fila[$tipoKey] ?? ''))) : '';

        if ($tipoKey !== null && $tipoRaw !== '') {
            if (strpos($tipoRaw, 'REDUCE') !== false && strpos($tipoRaw, 'TOTAL') !== false) {
                return $this->filaReduce($fecha, $maquina, $totalVenta);
            }
            if (strpos($tipoRaw, 'FISCAL') !== false && strpos($tipoRaw, 'UNITARIA') !== false) {
                if ($maquina === '') {
                    $razonRechazo = 'FISCAL UNITARIA pero máquina vacía (tipo="' . $tipoRaw . '")';
                    return null;
                }
                $num = preg_replace('/\D/', '', $factura);
                if ($num === '') {
                    $razonRechazo = 'FISCAL UNITARIA pero factura sin números (factura="' . substr($factura, 0, 30) . '")';
                    return null;
                }
                return $this->filaUnitaria($fecha, $maquina, $totalVenta, $num);
            }
            if (strpos($tipoRaw, 'FISCAL') !== false && strpos($tipoRaw, 'RANGO') !== false) {
                if ($maquina === '') {
                    $razonRechazo = 'FISCAL RANGO pero máquina vacía (tipo="' . $tipoRaw . '")';
                    return null;
                }
                if ($factura !== '' && preg_match('/^(\d+)\s*[-–]\s*(\d+)$/u', trim($factura), $m)) {
                    return $this->filaRango($fecha, $maquina, $totalVenta, (int) $m[1], (int) $m[2]);
                }
                $razonRechazo = 'FISCAL RANGO pero factura no tiene formato inicio-fin (factura="' . substr($factura, 0, 40) . '")';
                return null;
            }
        }

        if ($maquina === '') {
            $razonRechazo = 'sin TIPO reconocido y máquina vacía (tipo_raw="' . substr($tipoRaw ?? '', 0, 50) . '")';
            return null;
        }

        if ($factura !== '' && preg_match('/^(\d+)\s*[-–]\s*(\d+)$/u', trim($factura), $m)) {
            return $this->filaRango($fecha, $maquina, $totalVenta, (int) $m[1], (int) $m[2]);
        }

        $num = preg_replace('/\D/', '', $factura);
        if ($num !== '') {
            return $this->filaUnitaria($fecha, $maquina, $totalVenta, $num);
        }

        return $this->filaReduce($fecha, $maquina, $totalVenta);
    }

    protected function filaRango(string $fecha, string $maquina, string $totalVenta, int $a, int $b): array
    {
        $inicio = min($a, $b);
        $fin = max($a, $b);
        return [
            'fecha'           => $fecha,
            'maquina_fiscal'  => $maquina,
            'total_venta'     => $totalVenta,
            'factura_inicio'  => (string) $inicio,
            'factura_fin'     => (string) $fin,
            'cantidad'        => $fin - $inicio + 1,
            'tipo'            => self::TIPO_FISCAL_RANGO,
        ];
    }

    protected function filaUnitaria(string $fecha, string $maquina, string $totalVenta, string $num): array
    {
        return [
            'fecha'           => $fecha,
            'maquina_fiscal'  => $maquina,
            'total_venta'     => $totalVenta,
            'factura_inicio'  => $num,
            'factura_fin'     => $num,
            'cantidad'        => 1,
            'tipo'            => self::TIPO_FISCAL_UNITARIA,
        ];
    }

    protected function filaReduce(string $fecha, string $maquina, string $totalVenta): array
    {
        return [
            'fecha'           => $fecha,
            'maquina_fiscal'  => $maquina,
            'total_venta'     => $totalVenta,
            'factura_inicio'  => null,
            'factura_fin'     => null,
            'cantidad'        => 0,
            'tipo'            => self::TIPO_REDUCE_DIA,
        ];
    }

    /**
     * Lee y normaliza un archivo completo. Devuelve filas normalizadas (sin agrupar).
     */
    public function leerNormalizado(string $path): array
    {
        $this->razonesRechazo = [];
        $this->avisos = [];
        $filas = $this->leerArchivo($path);
        if (empty($filas)) {
            return [];
        }
        $columnMap = $this->detectarColumnas(array_keys($filas[0]));
        $normalizadas = [];
        foreach ($filas as $idx => $fila) {
            $razon = null;
            $n = $this->normalizarFila($fila, $columnMap, $razon);
            if ($n === null) {
                $this->rechazar(basename($path), (int) ($fila['_fila'] ?? ($idx + 2)), $razon ?? 'desconocido');
                continue;
            }
            $normalizadas[] = $n;
        }
        return $normalizadas;
    }

    /**
     * Un grupo por fila del libro: cada Z (FISCAL RANGO) y cada factura FISCAL UNITARIA con su propio monto, para que el
     * cuadre coincida fila por fila y no solo en la suma del día y la máquina. Las REDUCE con máquina (notas de crédito)
     * se restan al primer grupo de esa máquina ese día; las REDUCE sin máquina, al primer grupo del día.
     * Mismo formato de salida que agregarPorDiaMaquina, ordenado por fecha, máquina y primera factura.
     */
    public function agregarPorFila(array $normalizadas): array
    {
        $grupos = [];
        $reducciones = [];
        foreach ($normalizadas as $row) {
            if ($row['factura_inicio'] === null || $row['factura_inicio'] === '' || (int) $row['cantidad'] < 1) {
                $reducciones[] = $row;
                continue;
            }
            $grupos[] = [
                'fecha'           => $row['fecha'],
                'maquina_fiscal'  => $row['maquina_fiscal'],
                'total_venta'     => $row['total_venta'],
                'factura_inicio'  => (string) $row['factura_inicio'],
                'factura_fin'     => (string) ($row['factura_fin'] ?? $row['factura_inicio']),
                'cantidad'        => (int) $row['cantidad'],
                'numeros'         => range((int) $row['factura_inicio'], (int) ($row['factura_fin'] ?? $row['factura_inicio'])),
            ];
        }
        usort($grupos, function ($a, $b) {
            return [$a['fecha'], $a['maquina_fiscal'], (int) $a['factura_inicio']] <=> [$b['fecha'], $b['maquina_fiscal'], (int) $b['factura_inicio']];
        });

        foreach ($reducciones as $r) {
            if (bccomp((string) $r['total_venta'], '0', $this->scale + 2) === 0) {
                continue;
            }
            $destino = null;
            foreach ($grupos as $i => $g) {
                if ($g['fecha'] === $r['fecha'] && ($r['maquina_fiscal'] === '' || $g['maquina_fiscal'] === $r['maquina_fiscal'])) {
                    $destino = $i;
                    break;
                }
            }
            if ($destino === null && $r['maquina_fiscal'] !== '') {
                foreach ($grupos as $i => $g) {
                    if ($g['fecha'] === $r['fecha']) {
                        $destino = $i;
                        break;
                    }
                }
            }
            if ($destino !== null) {
                $grupos[$destino]['total_venta'] = bcadd($grupos[$destino]['total_venta'], $r['total_venta'], $this->scale + 2);
            }
        }

        return $grupos;
    }

    /**
     * Agrupa filas por (fecha, maquina_fiscal). Suma total_venta (positivos FISCAL RANGO/UNITARIA y negativos REDUCE).
     * Combina rangos/unitarias: factura_inicio = min, factura_fin = max, cantidad = suma.
     * Si hay REDUCE con máquina vacía (reducción del día), se resta del primer grupo de esa fecha.
     */
    public function agregarPorDiaMaquina(array $normalizadas): array
    {
        $grupos = [];
        $reduccionPorDia = [];

        foreach ($normalizadas as $row) {
            $key = $row['fecha'] . '|' . $row['maquina_fiscal'];
            if ($row['maquina_fiscal'] === '') {
                $reduccionPorDia[$row['fecha']] = bcadd($reduccionPorDia[$row['fecha']] ?? '0', $row['total_venta'], $this->scale + 2);
                continue;
            }
            // Números de factura reales de la fila: las FISCAL UNITARIA de un día pueden no ser consecutivas
            // (SERIE R 2, 7 y 8): el cuadre debe asignar esos números, no 2, 3 y 4.
            $numerosFila = ($row['factura_inicio'] !== null && $row['factura_inicio'] !== '')
                ? range((int) $row['factura_inicio'], (int) ($row['factura_fin'] ?? $row['factura_inicio']))
                : [];
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'fecha'           => $row['fecha'],
                    'maquina_fiscal'  => $row['maquina_fiscal'],
                    'total_venta'     => $row['total_venta'],
                    'factura_inicio'  => $row['factura_inicio'],
                    'factura_fin'     => $row['factura_fin'],
                    'cantidad'        => (int) $row['cantidad'],
                    'numeros'         => $numerosFila,
                ];
            } else {
                $grupos[$key]['numeros'] = array_merge($grupos[$key]['numeros'], $numerosFila);
                $grupos[$key]['total_venta'] = bcadd($grupos[$key]['total_venta'], $row['total_venta'], $this->scale + 2);
                if ($row['factura_inicio'] !== null && $row['factura_inicio'] !== '') {
                    $inicio = (int) $row['factura_inicio'];
                    $fin = (int) ($row['factura_fin'] ?? $row['factura_inicio']);
                    $cant = (int) $row['cantidad'];
                    $grupos[$key]['factura_inicio'] = $grupos[$key]['factura_inicio'] !== null && $grupos[$key]['factura_inicio'] !== ''
                        ? (string) min((int) $grupos[$key]['factura_inicio'], $inicio)
                        : (string) $inicio;
                    $grupos[$key]['factura_fin'] = $grupos[$key]['factura_fin'] !== null && $grupos[$key]['factura_fin'] !== ''
                        ? (string) max((int) $grupos[$key]['factura_fin'], $fin)
                        : (string) $fin;
                    $grupos[$key]['cantidad'] += $cant;
                }
            }
        }

        // Grupos que solo tienen REDUCE (sin factura/cantidad) pasan su monto a reducción del día.
        foreach ($grupos as $key => $g) {
            if (($g['factura_inicio'] === null || $g['factura_inicio'] === '' || $g['cantidad'] < 1) && bccomp($g['total_venta'], '0', $this->scale + 2) !== 0) {
                $reduccionPorDia[$g['fecha']] = bcadd($reduccionPorDia[$g['fecha']] ?? '0', $g['total_venta'], $this->scale + 2);
            }
        }

        $ordenados = array_values(array_filter($grupos, function ($g) {
            return $g['factura_inicio'] !== null && $g['factura_inicio'] !== '' && $g['cantidad'] >= 1;
        }));
        foreach ($ordenados as $i => $g) {
            $numeros = array_values(array_unique($g['numeros']));
            sort($numeros);
            $ordenados[$i]['numeros'] = $numeros;
        }

        usort($ordenados, function ($a, $b) {
            $c = strcmp($a['fecha'], $b['fecha']);
            return $c !== 0 ? $c : strcmp($a['maquina_fiscal'], $b['maquina_fiscal']);
        });

        $primeraMaquinaPorFecha = [];
        foreach ($ordenados as $g) {
            $f = $g['fecha'];
            if (!isset($primeraMaquinaPorFecha[$f])) {
                $primeraMaquinaPorFecha[$f] = $g['fecha'] . '|' . $g['maquina_fiscal'];
            }
        }

        foreach ($ordenados as $i => $g) {
            $key = $g['fecha'] . '|' . $g['maquina_fiscal'];
            $reduc = $reduccionPorDia[$g['fecha']] ?? '0';
            if (bccomp($reduc, '0', $this->scale + 2) !== 0 && $primeraMaquinaPorFecha[$g['fecha']] === $key) {
                $ordenados[$i]['total_venta'] = bcadd($g['total_venta'], $reduc, $this->scale + 2);
            }
        }

        return $ordenados;
    }

    /**
     * Fusiona varios archivos (CSV/XLSX) en un solo CSV canónico ordenado por fecha y máquina.
     * Devuelve estadísticas: filas por archivo, rechazadas, rango de fechas, total por mes.
     *
     * @param string[] $archivos
     */
    public function fusionarEnCsv(array $archivos, string $destino): array
    {
        $todas = [];
        $stats = ['archivos' => [], 'rechazadas' => [], 'avisos' => [], 'filas' => 0];
        foreach ($archivos as $archivo) {
            $normalizadas = $this->leerNormalizado($archivo);
            $stats['archivos'][basename($archivo)] = count($normalizadas);
            $stats['rechazadas'] = array_merge($stats['rechazadas'], $this->razonesRechazo);
            $stats['avisos'] = array_merge($stats['avisos'], $this->avisos);
            foreach ($normalizadas as $n) {
                $n['_origen'] = basename($archivo);
                $todas[] = $n;
            }
        }

        // Detecta duplicados exactos (misma fecha, máquina, rango, monto) entre archivos.
        $vistos = [];
        $duplicadas = 0;
        $unicas = [];
        foreach ($todas as $n) {
            $k = implode('|', [$n['fecha'], $n['maquina_fiscal'], $n['tipo'], $n['factura_inicio'] ?? '', $n['factura_fin'] ?? '', $n['total_venta']]);
            if (isset($vistos[$k])) {
                $duplicadas++;
                continue;
            }
            $vistos[$k] = true;
            $unicas[] = $n;
        }
        $stats['duplicadas_omitidas'] = $duplicadas;

        usort($unicas, function ($a, $b) {
            $c = strcmp($a['fecha'], $b['fecha']);
            if ($c !== 0) return $c;
            $c = strcmp($a['maquina_fiscal'], $b['maquina_fiscal']);
            if ($c !== 0) return $c;
            return ((int) ($a['factura_inicio'] ?? 0)) <=> ((int) ($b['factura_inicio'] ?? 0));
        });

        $dir = dirname($destino);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fh = fopen($destino, 'w');
        if ($fh === false) {
            throw new \RuntimeException("No se pudo escribir {$destino}");
        }
        fputcsv($fh, self::CABECERA_CANONICA);
        $porMes = [];
        $fechas = [];
        $porTipo = [];
        foreach ($unicas as $n) {
            if (!isset($porTipo[$n['tipo']])) {
                $porTipo[$n['tipo']] = ['filas' => 0, 'venta' => '0', 'conceptos' => []];
            }
            $porTipo[$n['tipo']]['filas']++;
            $porTipo[$n['tipo']]['venta'] = bcadd($porTipo[$n['tipo']]['venta'], $n['total_venta'], $this->scale);
            $porTipo[$n['tipo']]['conceptos'][$n['maquina_fiscal'] !== '' ? $n['maquina_fiscal'] : '(día)'] = true;
            $factura = '';
            if ($n['tipo'] === self::TIPO_FISCAL_RANGO) {
                $factura = $n['factura_inicio'] . '-' . $n['factura_fin'];
            } elseif ($n['tipo'] === self::TIPO_FISCAL_UNITARIA) {
                $factura = (string) $n['factura_inicio'];
            }
            fputcsv($fh, [
                $n['fecha'], $n['maquina_fiscal'], '', '', '', '', '', $factura,
                $this->formatearMonto($n['total_venta']), $n['tipo'],
            ]);
            $mes = substr($n['fecha'], 0, 7);
            if (!isset($porMes[$mes])) {
                $porMes[$mes] = ['venta' => '0', 'facturas' => 0, 'dias' => [], 'maquinas' => []];
            }
            $porMes[$mes]['venta'] = bcadd($porMes[$mes]['venta'], $n['total_venta'], $this->scale);
            $porMes[$mes]['facturas'] += (int) $n['cantidad'];
            $porMes[$mes]['dias'][$n['fecha']] = true;
            if ($n['maquina_fiscal'] !== '') {
                $porMes[$mes]['maquinas'][$n['maquina_fiscal']] = true;
            }
            $fechas[] = $n['fecha'];
        }
        fclose($fh);

        foreach ($porMes as $mes => $d) {
            $porMes[$mes]['dias'] = count($d['dias']);
            $porMes[$mes]['maquinas'] = array_keys($d['maquinas']);
        }
        ksort($porMes);
        foreach ($porTipo as $tipo => $d) {
            $porTipo[$tipo]['conceptos'] = array_keys($d['conceptos']);
        }
        $stats['filas'] = count($unicas);
        $stats['por_mes'] = $porMes;
        $stats['por_tipo'] = $porTipo;
        $stats['fecha_min'] = $fechas ? min($fechas) : null;
        $stats['fecha_max'] = $fechas ? max($fechas) : null;
        $stats['grupos'] = count($this->agregarPorDiaMaquina($unicas));
        return $stats;
    }

    protected function formatearMonto(string $monto): string
    {
        // Sin separador de miles, punto decimal, hasta 4 decimales sin ceros sobrantes.
        $s = bcadd($monto, '0', $this->scale);
        $s = rtrim(rtrim($s, '0'), '.');
        if ($s === '' || $s === '-') {
            $s = '0';
        }
        if (strpos($s, '.') === false) {
            $s .= '.00';
        }
        return $s;
    }
}
