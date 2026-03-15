<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder que actualiza "tasa" y "monto_bs" en items_pedidos según la tasa BCV del día
 * correspondiente a created_at. monto_bs = tasa del día × monto. Usa el CSV de tasas BCV (2023, 2024, 2025).
 *
 * Uso (ruta se pasa en el comando):
 *   php artisan tasas-bcv:seed "C:\ruta\al\Tasas BCV 2023 2024 2025.csv"
 *   php artisan tasas-bcv:seed   (usa database/data/Tasas_BCV_2023_2024_2025.csv)
 */
class TasasBcvItemsPedidosSeeder extends Seeder
{
    protected string $csvPath;

    public function __construct(?string $csvPath = null)
    {
        $this->csvPath = $csvPath ?? base_path('database/data/Tasas_BCV_2023_2024_2025.csv');
    }

    public function run($stepBar = null): void
    {
        $tasasPorFecha = $this->extraerTasasDelCsv();
        if (empty($tasasPorFecha)) {
            if ($this->command) {
                $this->command->error('No se pudieron extraer tasas del CSV. Revisa la ruta y el formato.');
            }
            return;
        }

        if ($this->command) {
            $this->command->info('Tasas cargadas: ' . count($tasasPorFecha) . ' fechas.');
        }
        $this->actualizarTasaEnItemsPedidos($tasasPorFecha);
    }

    /**
     * Parsea el CSV de tasas BCV y devuelve un array [ 'Y-m-d' => tasa (float) ]
     * con forward-fill para fines de semana y feriados.
     */
    protected function extraerTasasDelCsv(): array
    {
        if (!is_file($this->csvPath)) {
            if ($this->command) {
                $this->command->warn("Archivo no encontrado: {$this->csvPath}");
            }
            return [];
        }

        $lines = file($this->csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }

        $tasasPorFecha = [];
        $year = null;
        $months = []; // [1,2,3,4] o [5,6,7,8] o [9,10,11,12]
        $dataStartIndex = -1;
        $rowIndex = 0;

        foreach ($lines as $line) {
            $rowIndex++;
            $decoded = $this->parseCsvLine($line);
            if (empty($decoded)) {
                continue;
            }

            // Detectar año y cuatrimestre
            $firstCell = trim($decoded[0] ?? '');
            if (preg_match('/Primer\s+cuatrimestre\s+(\d{4})/ui', $firstCell, $m)) {
                $year = (int) $m[1];
                $months = [1, 2, 3, 4];
                $dataStartIndex = $rowIndex + 3; // 2 líneas de cabecera (MES DE..., DÍA/BCV)
                continue;
            }
            if (preg_match('/Segundo\s+cuatrimestre\s+(\d{4})/ui', $firstCell, $m)) {
                $year = (int) $m[1];
                $months = [5, 6, 7, 8];
                $dataStartIndex = $rowIndex + 3;
                continue;
            }
            if (preg_match('/Tercer\s+cuatrimestre\s+(\d{4})/ui', $firstCell, $m)) {
                $year = (int) $m[1];
                $months = [9, 10, 11, 12];
                $dataStartIndex = $rowIndex + 3;
                continue;
            }
            if (preg_match('/Tabla\s+cambiaria\s+diaria\s+(\d{4})/ui', $firstCell, $m)) {
                $year = (int) $m[1];
                continue;
            }

            // Filas de datos: empiezan con número (día) o están justo después del bloque
            if ($year === null || $dataStartIndex < 0 || $rowIndex < $dataStartIndex) {
                continue;
            }
            if ($rowIndex > $dataStartIndex + 31) {
                $dataStartIndex = -1;
                continue;
            }

            // Cada fila tiene 12 columnas: 4 meses x (DÍA, BCV, PARALELO)
            // Columnas BCV: 1, 4, 7, 10
            // Solo guardar días con tasa real; el forward-fill posterior cubre fines de semana/feriados.
            foreach ([0, 1, 2, 3] as $monthIdx) {
                $colDia = $monthIdx * 3;
                $colBcv = $monthIdx * 3 + 1;
                $day = isset($decoded[$colDia]) ? trim($decoded[$colDia]) : '';
                $bcvRaw = isset($decoded[$colBcv]) ? trim($decoded[$colBcv]) : '';

                if ($day === '' || !ctype_digit($day)) {
                    continue;
                }
                $day = (int) $day;
                $month = $months[$monthIdx] ?? null;
                if ($month === null || $day < 1 || $day > 31) {
                    continue;
                }
                if (!checkdate($month, $day, $year)) {
                    continue;
                }

                $tasa = $this->extraerTasaBcv($bcvRaw);
                $fecha = sprintf('%04d-%02d-%02d', $year, $month, $day);
                if ($tasa !== null && $tasa > 0) {
                    $tasasPorFecha[$fecha] = $tasa;
                }
                // No guardar nada para fines de semana/feriados; el forward-fill los llenará correctamente.
            }
        }

        ksort($tasasPorFecha);

        // Sábados, domingos y feriados: usar la última tasa válida (forward-fill + backward-fill)
        $rangoMin = min(array_keys($tasasPorFecha));
        $rangoMax = max(array_keys($tasasPorFecha));
        $filled = [];

        // Forward-fill: cada día sin tasa toma la tasa del último día hábil anterior
        $ultima = null;
        $current = $rangoMin;
        while ($current <= $rangoMax) {
            if (isset($tasasPorFecha[$current])) {
                $ultima = $tasasPorFecha[$current];
                $filled[$current] = $ultima;
            } else {
                $filled[$current] = $ultima;
            }
            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }

        // Backward-fill: los primeros días del rango (si aún son null) usan la primera tasa válida
        $primeraTasa = null;
        foreach ($filled as $tasa) {
            if ($tasa !== null) {
                $primeraTasa = $tasa;
                break;
            }
        }
        if ($primeraTasa !== null) {
            ksort($filled);
            foreach ($filled as $f => $tasa) {
                if ($tasa === null) {
                    $filled[$f] = $primeraTasa;
                } else {
                    break; // ya no hay nulls al inicio
                }
            }
        }

        return $filled;
    }

    /**
     * Parsea una línea CSV respetando comillas (ej. "Bs. 36,42").
     */
    protected function parseCsvLine(string $line): array
    {
        $result = [];
        $len = strlen($line);
        $current = '';
        $inQuotes = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($c === '"') {
                $inQuotes = !$inQuotes;
                continue;
            }
            if (!$inQuotes && $c === ',') {
                $result[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $result[] = $current;
        return $result;
    }

    /**
     * Extrae valor numérico de la celda BCV (ej. "Bs. 17.49", "Bs. 24 80", "52.03", "Bs. 36,42").
     */
    protected function extraerTasaBcv(string $valor): ?float
    {
        $valor = preg_replace('/\s*Bs\.?\s*/ui', '', $valor);
        $valor = trim($valor);
        // Ignorar textos que no son tasas
        if (preg_match('/^(domingo|sábado|sabado|feriado|Feriado|J\.?\s*Santo|V\.?\s*Santo|Jueves Santo|Viernes Santo|Navidad|Bancario|Bs\.?\s*Navidad)$/ui', $valor)) {
            return null;
        }
        if ($valor === '' || $valor === 'Bs.' || $valor === 'Bs') {
            return null;
        }
        $valor = str_replace(',', '.', $valor);
        $valor = preg_replace('/\s+/', '.', $valor);
        $valor = preg_replace('/[^\d.]/', '', $valor);
        if ($valor === '') {
            return null;
        }
        $num = (float) $valor;
        return $num > 0 ? round($num, 4) : null;
    }

    /**
     * Actualiza tasa, monto y monto_bs en items_pedidos según la fecha created_at.
     * monto = cantidad × precio_unitario (USD).
     * monto_bs = monto × tasa.
     */
    protected function actualizarTasaEnItemsPedidos(array $tasasPorFecha): void
    {
        $tabla = 'items_pedidos';
        $total = DB::table($tabla)->count();
        $actualizados = 0;
        $sinTasa = 0;

        $bar = null;
        if ($this->command && $total > 0) {
            $bar = $this->command->getOutput()->createProgressBar($total);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — Tasa, monto y monto_bs');
            $bar->start();
        }

        DB::table($tabla)->orderBy('id')->chunk(500, function ($items) use ($tasasPorFecha, &$actualizados, &$sinTasa, $bar) {
            foreach ($items as $item) {
                if ($bar) {
                    $bar->advance();
                }
                $createdAt = $item->created_at;
                if (!$createdAt) {
                    $sinTasa++;
                    continue;
                }
                $fecha = date('Y-m-d', strtotime($createdAt));
                $tasa = $tasasPorFecha[$fecha] ?? null;
                if ($tasa === null) {
                    $sinTasa++;
                    continue;
                }
                $cantidad = (float) ($item->cantidad ?? 0);
                $precioUnitario = (float) ($item->precio_unitario ?? 0);
                $monto = $precioUnitario > 0 && $cantidad > 0
                    ? round($cantidad * $precioUnitario, 4)
                    : (float) ($item->monto ?? 0);
                $monto_bs = round($monto * $tasa, 4);
                DB::table('items_pedidos')->where('id', $item->id)->update([
                    'tasa'     => $tasa,
                    'monto'    => $monto,
                    'monto_bs' => $monto_bs,
                ]);
                $actualizados++;
            }
        });

        if ($bar) {
            $bar->finish();
            if ($this->command) {
                $this->command->newLine();
            }
        }
        if ($this->command) {
            $this->command->info("[3/3] Items actualizados (tasa, monto y monto_bs): {$actualizados}. Sin tasa para la fecha: {$sinTasa}.");
        }
    }
}
