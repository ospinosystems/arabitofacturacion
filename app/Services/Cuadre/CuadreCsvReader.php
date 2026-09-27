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
     * con los mismos criterios que el CSV (fechas Excel → YYYY-MM-DD).
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
                    }
                    if ($val !== null && $val !== '' && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($val)) {
                        $val = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)->format('Y-m-d');
                    }
                    $s = is_scalar($val) ? trim((string) $val) : '';
                    if ($s !== '') {
                        $vacia = false;
                    }
                    $fila[] = $s;
                }
                if (!$vacia) {
                    $rows[] = $fila;
                }
            }
            if (count($rows) < 2) {
                continue;
            }
            $header = array_map('trim', $rows[0]);
            $lastIndex = $this->ultimoIndiceUtil($header);
            $header = array_slice($header, 0, $lastIndex + 1);
            for ($i = 1; $i < count($rows); $i++) {
                $row = array_pad($rows[$i], count($header), '');
                $row = array_slice($row, 0, $lastIndex + 1);
                $assoc = @array_combine($header, $row);
                if ($assoc !== false) {
                    $out[] = $assoc;
                }
            }
            // Solo la primera hoja con datos.
            break;
        }
        $spreadsheet->disconnectWorksheets();
        return $out;
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
                if (count($this->razonesRechazo) < 50) {
                    $this->razonesRechazo[] = sprintf('%s fila %d: %s', basename($path), $idx + 2, $razon ?? 'desconocido');
                }
                continue;
            }
            $normalizadas[] = $n;
        }
        return $normalizadas;
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
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'fecha'           => $row['fecha'],
                    'maquina_fiscal'  => $row['maquina_fiscal'],
                    'total_venta'     => $row['total_venta'],
                    'factura_inicio'  => $row['factura_inicio'],
                    'factura_fin'     => $row['factura_fin'],
                    'cantidad'        => (int) $row['cantidad'],
                ];
            } else {
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
        $stats = ['archivos' => [], 'rechazadas' => [], 'filas' => 0];
        foreach ($archivos as $archivo) {
            $normalizadas = $this->leerNormalizado($archivo);
            $stats['archivos'][basename($archivo)] = count($normalizadas);
            $stats['rechazadas'] = array_merge($stats['rechazadas'], $this->razonesRechazo);
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
        foreach ($unicas as $n) {
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
        $stats['filas'] = count($unicas);
        $stats['por_mes'] = $porMes;
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
