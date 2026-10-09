<?php

namespace App\Services\Inventario;

/**
 * Libro de entradas y salidas de inventario en PDF escrito directamente (sin DomPDF): tabla de texto en Helvetica,
 * carta horizontal, salto de página automático con cabecera repetida y numeración. Usa unos pocos MB de memoria y
 * tarda segundos con miles de productos, por lo que el PDF de cualquier período sale desde la web (PHP-FPM tiene
 * 512 MB fijos y DomPDF necesitaba más de 1,5 GB para 7.000 productos) y desde la consola con el mismo código.
 */
class LibroInventarioPdf
{
    /** Anchos Helvetica (= Arial) en milésimas de em para los caracteres 32..126; los acentuados usan ~556. */
    private const ANCHOS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    private const ANCHO_PAG = 792.0;   // carta horizontal
    private const ALTO_PAG = 612.0;
    private const MARGEN = 20.0;
    private const FS = 6.8;            // tamaño de letra del cuerpo
    private const LH = 8.4;            // alto de línea del cuerpo
    private const PAD = 2.0;           // relleno horizontal de celda

    /** [título, ancho, alineación (l|r), clave] */
    private const COLUMNAS = [
        ['Código', 58, 'l', 'codigo'], ['Descripción', 190, 'l', 'descripcion'], ['Unid.', 26, 'l', 'unidad'],
        ['Exist. inicial', 44, 'r', 'ini_qty'], ['Entradas', 44, 'r', 'ent_qty'], ['Costo entradas USD', 52, 'r', 'ent_valor'],
        ['Salidas', 44, 'r', 'sal_qty'], ['Costo salidas USD', 52, 'r', 'sal_valor'], ['Devol.', 34, 'r', 'dev_qty'],
        ['Existencia final', 48, 'r', 'fin_qty'], ['Costo prom. USD', 46, 'r', 'prom'], ['Valor final USD', 52, 'r', 'fin_valor'], ['Valor final Bs', 62, 'r', 'fin_valor_bs'],
    ];
    private const DECIMALES = ['ini_qty' => 2, 'ent_qty' => 2, 'ent_valor' => 2, 'sal_qty' => 2, 'sal_valor' => 2, 'dev_qty' => 2, 'fin_qty' => 2, 'prom' => 4, 'fin_valor' => 2, 'fin_valor_bs' => 2];

    /** @var string[] contenido (operadores PDF) de cada página */
    private array $paginas = [];
    private string $op = '';
    private float $y = 0.0;
    private array $cabeceraCorta = [];

    /**
     * @param array $libro resultado de LibroInventarioService::construir()
     * @param array $productos filas a listar (ya filtradas)
     * @return string bytes del PDF
     */
    public function generar(array $libro, array $productos, ?object $empresa): string
    {
        $fmt = fn ($n, int $d = 2) => number_format((float) $n, $d, ',', '.');
        $this->paginas = [];
        $this->cabeceraCorta = [
            trim(($empresa->nombre_registro ?? '') . ' · RIF ' . ($empresa->rif ?? '') . ' · ' . ($empresa->sucursal ?? '')),
            'Período ' . $libro['desde'] . ' al ' . $libro['hasta'] . ' · Entradas: ' . implode(', ', $libro['tipos']),
        ];
        $this->nuevaPagina(true);

        // Encabezado completo (solo primera página)
        $this->texto(self::ANCHO_PAG / 2, $this->y, 'REGISTRO DE ENTRADAS Y SALIDAS DE INVENTARIO', 11, true, 'c');
        $this->y -= 14;
        $lineas = [
            trim(($empresa->nombre_registro ?? '') . ' · RIF ' . ($empresa->rif ?? '')),
            trim(($empresa->sucursal ?? '') . (!empty($empresa->direccion_sucursal) ? ' · ' . $empresa->direccion_sucursal : '')),
            'Período: ' . $libro['desde'] . ' al ' . $libro['hasta'] . ' · Entradas consideradas: ' . implode(', ', $libro['tipos'])
                . ' · Método de valoración: costo promedio ponderado · Tasa de cierre: ' . $fmt($libro['tasa_cierre'], 4) . ' Bs/USD',
        ];
        foreach ($lineas as $l) {
            if ($l === '') continue;
            $this->texto(self::ANCHO_PAG / 2, $this->y, $l, 7.5, false, 'c');
            $this->y -= 9.5;
        }
        $this->y -= 4;

        // Tabla
        $this->filaCabecera();
        $tot = array_fill_keys(array_keys(self::DECIMALES), 0.0);
        $n = 0;
        foreach ($productos as $p) {
            $n++;
            foreach ($tot as $k => $v) {
                if ($k !== 'prom') $tot[$k] += (float) ($p[$k] ?? 0);
            }
            $celdas = [];
            foreach (self::COLUMNAS as [$tit, $w, $al, $k]) {
                $celdas[] = isset(self::DECIMALES[$k]) ? $fmt($p[$k] ?? 0, self::DECIMALES[$k]) : (string) ($p[$k] ?? '');
            }
            $this->fila($celdas, false, null, (float) ($p['fin_qty'] ?? 0) < -0.00001);
        }
        $celdas = [];
        foreach (self::COLUMNAS as $i => [$tit, $w, $al, $k]) {
            $celdas[] = $i === 0 ? 'TOTALES (' . $fmt($n, 0) . ' productos)' : ($i < 3 || $k === 'prom' ? '' : $fmt($tot[$k], self::DECIMALES[$k]));
        }
        $this->fila($celdas, true, [0.91, 0.96, 0.99], false, 3);

        // Pie explicativo
        $this->y -= 6;
        $pie = 'Existencia final = existencia inicial + entradas - salidas + devoluciones. La existencia inicial es la acumulada por los documentos '
            . 'anteriores al inicio del período (el libro parte del primer documento registrado, sin stock histórico). Entradas: documentos de compra '
            . 'recibidos en la sucursal según los tipos indicados (facturas fiscales con su N° de factura del proveedor, notas de entrega y traslados entre '
            . 'sucursales). Salidas: facturas de venta (número de factura y máquina fiscal); las devoluciones reingresan al mismo costo. Las salidas se '
            . 'valoran al costo promedio ponderado de las entradas de cada producto. Los productos con existencia negativa indican ventas sin factura de '
            . 'compra previa registrada. Generado el ' . date('d/m/Y H:i') . '.';
        foreach ($this->envolver($pie, self::ANCHO_PAG - 2 * self::MARGEN, 6.5, 50) as $l) {
            if ($this->y < self::MARGEN + 14) $this->nuevaPagina(false);
            $this->texto(self::MARGEN, $this->y, $l, 6.5, false, 'l', [0.27, 0.27, 0.27]);
            $this->y -= 8;
        }
        return $this->ensamblar();
    }

    // ── Composición ────────────────────────────────────────────────────────────────────────────────

    private function nuevaPagina(bool $primera): void
    {
        if ($this->op !== '') $this->paginas[] = $this->op;
        $this->op = '';
        $this->y = self::ALTO_PAG - self::MARGEN - 4;
        if (!$primera) {
            $this->texto(self::MARGEN, $this->y, $this->cabeceraCorta[0], 7, false, 'l', [0.27, 0.27, 0.27]);
            $this->texto(self::ANCHO_PAG - self::MARGEN, $this->y, $this->cabeceraCorta[1], 7, false, 'r', [0.27, 0.27, 0.27]);
            $this->y -= 11;
            $this->filaCabecera();
        }
    }

    private function filaCabecera(): void
    {
        $alto = 2 * self::LH + 3;
        if ($this->y - $alto < self::MARGEN + 12) $this->nuevaPagina(false);
        $x = self::MARGEN;
        $top = $this->y;
        foreach (self::COLUMNAS as [$tit, $w, $al, $k]) {
            $this->op .= sprintf("0.91 g %.2f %.2f %.2f %.2f re f 0 g\n", $x, $top - $alto, $w, $alto);
            $this->op .= sprintf("0.6 G 0.4 w %.2f %.2f %.2f %.2f re S\n", $x, $top - $alto, $w, $alto);
            $lineas = $this->envolver($tit, $w - 2 * self::PAD, 6.5, 2, true);
            $yy = $top - self::LH + 1.5 - (count($lineas) === 1 ? self::LH / 2 : 0);
            foreach ($lineas as $l) {
                $this->texto($x + $w / 2, $yy, $l, 6.5, true, 'c');
                $yy -= self::LH;
            }
            $x += $w;
        }
        $this->y -= $alto;
    }

    /**
     * Fila de datos: la descripción se envuelve hasta 2 líneas; el resto se recorta con puntos suspensivos.
     * $fusionar > 1 une las primeras N columnas en una sola celda (etiqueta de totales).
     */
    private function fila(array $celdas, bool $negrita, ?array $fondo, bool $resaltarNegativo = false, int $fusionar = 1): void
    {
        $columnas = self::COLUMNAS;
        if ($fusionar > 1) {
            $ancho = array_sum(array_map(fn ($c) => $c[1], array_slice($columnas, 0, $fusionar)));
            $columnas = array_merge([[$columnas[0][0], $ancho, 'l', $columnas[0][3]]], array_slice($columnas, $fusionar));
            $celdas = array_merge([$celdas[0]], array_slice($celdas, $fusionar));
        }
        $lineasPorCol = [];
        $maxLineas = 1;
        foreach ($columnas as $i => [$tit, $w, $al, $k]) {
            $maxL = $k === 'descripcion' ? 2 : 1;
            $lineasPorCol[$i] = $this->envolver($celdas[$i], $w - 2 * self::PAD, self::FS, $maxL, $negrita);
            $maxLineas = max($maxLineas, count($lineasPorCol[$i]));
        }
        $alto = $maxLineas * self::LH + 2;
        if ($this->y - $alto < self::MARGEN + 12) $this->nuevaPagina(false);
        $x = self::MARGEN;
        $top = $this->y;
        foreach ($columnas as $i => [$tit, $w, $al, $k]) {
            if ($fondo) {
                $this->op .= sprintf("%.2f %.2f %.2f rg %.2f %.2f %.2f %.2f re f 0 g\n", $fondo[0], $fondo[1], $fondo[2], $x, $top - $alto, $w, $alto);
            }
            $this->op .= sprintf("0.6 G 0.3 w %.2f %.2f %.2f %.2f re S\n", $x, $top - $alto, $w, $alto);
            $yy = $top - self::LH + 1.2;
            $color = ($resaltarNegativo && $k === 'fin_qty') ? [0.75, 0, 0] : null;
            foreach ($lineasPorCol[$i] as $l) {
                if ($al === 'r') {
                    $this->texto($x + $w - self::PAD, $yy, $l, self::FS, $negrita, 'r', $color);
                } else {
                    $this->texto($x + self::PAD, $yy, $l, self::FS, $negrita, 'l', $color);
                }
                $yy -= self::LH;
            }
            $x += $w;
        }
        $this->y -= $alto;
    }

    /** Escribe texto en (x, y) con alineación l (x = inicio), r (x = fin) o c (x = centro). */
    private function texto(float $x, float $y, string $s, float $fs, bool $negrita, string $al = 'l', ?array $color = null): void
    {
        $bytes = $this->cp1252($s);
        if ($bytes === '') return;
        $w = $this->ancho($bytes, $fs, $negrita);
        if ($al === 'r') $x -= $w;
        elseif ($al === 'c') $x -= $w / 2;
        $this->op .= sprintf("BT %s /F%d %.2f Tf %.2f %.2f Td (%s) Tj ET%s\n",
            $color ? sprintf('%.2f %.2f %.2f rg', $color[0], $color[1], $color[2]) : '', $negrita ? 2 : 1, $fs, $x, $y,
            strtr($bytes, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']), $color ? ' 0 g' : '');
    }

    /** Divide un texto en líneas que quepan en $ancho pt; más de $maxLineas se recortan con "...". */
    private function envolver(string $s, float $ancho, float $fs, int $maxLineas, bool $negrita = false): array
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        if ($s === '') return [''];
        if ($this->ancho($this->cp1252($s), $fs, $negrita) <= $ancho) return [$s];
        $lineas = [];
        $actual = '';
        foreach (preg_split('/ /', $s) as $palabra) {
            // palabra más ancha que la celda: cortarla por caracteres
            while ($this->ancho($this->cp1252($palabra), $fs, $negrita) > $ancho) {
                $corte = mb_strlen($palabra);
                while ($corte > 1 && $this->ancho($this->cp1252(($actual === '' ? '' : $actual . ' ') . mb_substr($palabra, 0, $corte)), $fs, $negrita) > $ancho) $corte--;
                if ($actual !== '' && $corte <= 1) { $lineas[] = $actual; $actual = ''; continue; }
                $lineas[] = ($actual === '' ? '' : $actual . ' ') . mb_substr($palabra, 0, $corte);
                $actual = '';
                $palabra = mb_substr($palabra, $corte);
            }
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if ($this->ancho($this->cp1252($prueba), $fs, $negrita) <= $ancho) {
                $actual = $prueba;
            } else {
                if ($actual !== '') $lineas[] = $actual;
                $actual = $palabra;
            }
        }
        if ($actual !== '') $lineas[] = $actual;
        if (count($lineas) > $maxLineas) {
            $lineas = array_slice($lineas, 0, $maxLineas);
            $ult = $lineas[$maxLineas - 1];
            while ($ult !== '' && $this->ancho($this->cp1252($ult . '...'), $fs, $negrita) > $ancho) $ult = mb_substr($ult, 0, -1);
            $lineas[$maxLineas - 1] = rtrim($ult) . '...';
        }
        return $lineas;
    }

    private function ancho(string $bytes, float $fs, bool $negrita = false): float
    {
        $w = 0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($bytes[$i]);
            $w += ($c >= 32 && $c <= 126) ? self::ANCHOS[$c - 32] : 556;
        }
        return $w * $fs / 1000 * ($negrita ? 1.07 : 1);
    }

    private function cp1252(string $s): string
    {
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($r === false) $r = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $r === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $r;
    }

    // ── Ensamblado del archivo PDF ─────────────────────────────────────────────────────────────────

    private function ensamblar(): string
    {
        if ($this->op !== '') $this->paginas[] = $this->op;
        $this->op = '';
        $total = count($this->paginas);
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        foreach ($this->paginas as $i => $contenido) {
            $pie = sprintf("BT /F1 6.5 Tf 0.27 0.27 0.27 rg %.2f %.2f Td (P\xe1gina %d de %d) Tj ET 0 g\n", self::ANCHO_PAG - self::MARGEN - 50, self::MARGEN - 8, $i + 1, $total);
            $flujo = gzcompress($contenido . $pie, 6);
            $objetos[$n] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0f %.0f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::ANCHO_PAG, self::ALTO_PAG, $n + 1);
            $objetos[$n + 1] = sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($flujo), $flujo);
            $kids[] = $n . ' 0 R';
            $n += 2;
        }
        $objetos[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), $total);
        ksort($objetos);
        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];
        foreach ($objetos as $id => $cuerpo) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$cuerpo\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objetos));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        $this->paginas = [];
        return $pdf;
    }
}
