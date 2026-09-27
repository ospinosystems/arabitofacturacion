<?php
/**
 * Extrae las tasas BCV diarias de las "Tablas cambiarias diarias" del anexo de Wikipedia
 * https://es.wikipedia.org/wiki/Anexo:Cotizaci%C3%B3n_hist%C3%B3rica_del_bol%C3%ADvar_con_respecto_al_d%C3%B3lar
 * y las guarda en un CSV simple (fecha,tasa_bcv), que es el formato por defecto de `php artisan tasas-bcv:seed`
 * (database/data/tasas_bcv_diarias.csv).
 *
 * Uso:
 *   php scripts/extraer_tasas_bcv_wikipedia.php                       # descarga la página y escribe database/data/tasas_bcv_diarias.csv
 *   php scripts/extraer_tasas_bcv_wikipedia.php pagina.html salida.csv # desde un HTML ya descargado
 *
 * Solo toma los días hábiles con valor (sábados, domingos y feriados los rellena el seeder con la última tasa) y descarta
 * valores absurdos (error de tipeo en la fuente: más del triple del día anterior y el siguiente vuelve a la normalidad).
 */

$url = 'https://es.wikipedia.org/wiki/Anexo:Cotizaci%C3%B3n_hist%C3%B3rica_del_bol%C3%ADvar_con_respecto_al_d%C3%B3lar';
$entrada = $argv[1] ?? null;
$salida = $argv[2] ?? __DIR__ . '/../database/data/tasas_bcv_diarias.csv';
$anioMinimo = 2023;

if ($entrada !== null && is_file($entrada)) {
    $html = file_get_contents($entrada);
} else {
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: arabitofacturacion/1.0 (tasas BCV)\r\n", 'timeout' => 60]]);
    $html = file_get_contents($url, false, $ctx);
}
if (!$html) {
    fwrite(STDERR, "No se pudo leer la página.\n");
    exit(1);
}

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xp = new DOMXPath($doc);

$mesesNombre = ['ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
    'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10, 'NOVIEMBRE' => 11, 'DICIEMBRE' => 12];
$texto = function ($fila) use ($xp) {
    $c = [];
    foreach ($xp->query('./th|./td', $fila) as $x) {
        $c[] = trim(preg_replace('/\s+/', ' ', preg_replace('/\[\d+\]/', '', $x->textContent)));
    }
    return $c;
};
$valor = function (string $v): ?float {
    $vv = trim(preg_replace('/\s*Bs\.?\s*/iu', '', $v));
    if ($vv === '' || preg_match('/^[A-Za-zÁÉÍÓÚáéíóúñÑ ]+$/u', $vv)) {
        return null; // sábado, domingo, feriado, Carnaval, Semana Santa…
    }
    $vv = preg_replace('/[^\d.]/', '', preg_replace('/\s+/', '.', str_replace(',', '.', $vv)));
    return (float) $vv > 0 ? round((float) $vv, 4) : null;
};

$tasas = [];
$avisos = [];
foreach ($xp->query('//table') as $t) {
    $rows = $xp->query('.//tr', $t);
    if ($rows->length < 5) {
        continue;
    }
    $r0 = $texto($rows->item(0));
    if (!preg_match('/cuatrimestre\s+(\d{4})/iu', $r0[0] ?? '', $m)) {
        continue;
    }
    $anio = (int) $m[1];
    if ($anio < $anioMinimo) {
        continue;
    }
    $r1 = $texto($rows->item(1)); // MES DE ENERO | MES DE FEBRERO | …
    $r2 = $texto($rows->item(2)); // DÍA | BCV | PARALELO | DÍA | …
    $meses = [];
    foreach ($r1 as $c) {
        if (preg_match('/MES DE\s+([A-ZÁÉÍÓÚ]+)/iu', mb_strtoupper($c), $mm)) {
            $n = $mesesNombre[str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $mm[1])] ?? null;
            if ($n) {
                $meses[] = $n;
            }
        }
    }
    // Cada "DÍA" abre un bloque de mes; su BCV es la siguiente columna que diga BCV (puede haber columnas extra, p. ej. "Promedio €").
    $bloques = [];
    for ($i = 0; $i < count($r2); $i++) {
        if (in_array(mb_strtoupper($r2[$i]), ['DÍA', 'DIA'], true)) {
            $bcv = null;
            for ($j = $i + 1; $j < count($r2) && $j <= $i + 3; $j++) {
                if (stripos($r2[$j], 'BCV') !== false) {
                    $bcv = $j;
                    break;
                }
            }
            $bloques[] = ['dia' => $i, 'bcv' => $bcv];
        }
    }
    if (count($bloques) !== count($meses)) {
        $avisos[] = "{$r0[0]}: bloques=" . count($bloques) . ' meses=' . count($meses);
    }
    for ($k = 3; $k < $rows->length; $k++) {
        $c = $texto($rows->item($k));
        if (count($c) !== count($r2)) {
            // Fila corta (p. ej. el día 31: los meses sin ese día no tienen celdas y las columnas se desplazan):
            // cada celda numérica es un día y la siguiente es su BCV; el mes es el siguiente que tenga ese día.
            $pm = 0;
            for ($i = 0; $i < count($c); $i++) {
                if (!ctype_digit($c[$i])) {
                    continue;
                }
                $dia = (int) $c[$i];
                while ($pm < count($meses) && !checkdate($meses[$pm], $dia, $anio)) {
                    $pm++;
                }
                if ($pm >= count($meses)) {
                    break;
                }
                $mes = $meses[$pm++];
                $v = $valor($c[$i + 1] ?? '');
                if ($v !== null) {
                    $tasas[sprintf('%04d-%02d-%02d', $anio, $mes, $dia)] = $v;
                }
            }
            continue;
        }
        foreach ($bloques as $bi => $b) {
            $mes = $meses[$bi] ?? null;
            if (!$mes || $b['bcv'] === null || !ctype_digit($c[$b['dia']] ?? '')) {
                continue;
            }
            $dia = (int) $c[$b['dia']];
            if (!checkdate($mes, $dia, $anio)) {
                continue;
            }
            $v = $valor($c[$b['bcv']] ?? '');
            if ($v !== null) {
                $tasas[sprintf('%04d-%02d-%02d', $anio, $mes, $dia)] = $v;
            }
        }
    }
}
ksort($tasas);

// Valores absurdos: más del triple del día hábil anterior y el siguiente vuelve a la normalidad.
$limpias = [];
$fechas = array_keys($tasas);
foreach ($fechas as $i => $f) {
    $b = $tasas[$f];
    $a = $i > 0 ? $tasas[$fechas[$i - 1]] : null;
    $c = $i < count($fechas) - 1 ? $tasas[$fechas[$i + 1]] : null;
    if ($a !== null && $c !== null && $b > $a * 3 && $c < $b / 3) {
        $avisos[] = "descartado $f=$b (vecinos $a y $c)";
        continue;
    }
    $limpias[$f] = $b;
}

$dir = dirname($salida);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$fh = fopen($salida, 'w');
fputcsv($fh, ['fecha', 'tasa_bcv']);
foreach ($limpias as $f => $t) {
    fputcsv($fh, [$f, number_format($t, 4, '.', '')]);
}
fclose($fh);

echo 'Fechas con tasa: ' . count($limpias) . ' | ' . array_key_first($limpias) . ' → ' . array_key_last($limpias) . " | escrito en {$salida}\n";
foreach ($avisos as $a) {
    echo "AVISO: {$a}\n";
}
