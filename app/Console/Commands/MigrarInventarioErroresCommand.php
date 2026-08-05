<?php

namespace App\Console\Commands;

use App\Http\Controllers\sendCentral;
use Illuminate\Console\Command;

/**
 * CSV con los errores de la mudanza de inventario (`inventario:migrar`).
 *
 * TitanioPOS reporta los fallos por número de FILA, sin descripción ni códigos:
 * inútil para corregir a mano. Este comando le pide a central esos errores YA
 * RESUELTOS contra los productos que se enviaron, y escribe el CSV AQUÍ, en la
 * tienda, que es donde se corrigen los productos.
 *
 * El CSV trae dos tipos de fila:
 *   · `titaniopos`               — lo que rechazó su API, con el producto completo.
 *   · `codigo_repetido_en_envio` — productos DISTINTOS de esta tienda que
 *     comparten el mismo código de barras. Es la causa típica del aborto
 *     "N filas duplicadas": TitanioPOS resuelve el producto por ese código, así
 *     que ambos caen en el mismo y el segundo se rechaza. Hay que unificarlos.
 *
 *   php artisan inventario:migrar-errores --referencia=valledelapascua2-20260805-161230
 *   php artisan inventario:migrar-errores --id=7 --salida=C:\errores.csv
 */
class MigrarInventarioErroresCommand extends Command
{
    protected $signature = 'inventario:migrar-errores
        {--referencia= : referencia que imprimió inventario:migrar (default: la última corrida)}
        {--id= : id de la migración (alternativa a la referencia)}
        {--salida= : ruta del CSV (default: storage/app/…)}';

    protected $description = 'Descarga de central los errores de la mudanza de inventario y genera un CSV con los productos.';

    public function handle(): int
    {
        // Sin --referencia se consulta la ÚLTIMA migración de esta tienda: cada
        // `inventario:migrar` sin --referencia crea una corrida nueva, y es fácil
        // terminar mirando una vieja.
        $ref = $this->option('referencia');
        $id  = $this->option('id');

        $sc = new sendCentral();
        $r = $sc->requestToCentral('POST', '/migracion/inventario/errores',
            array_filter(['referencia' => $ref, 'id' => $id]),
            ['timeout' => 180]);

        $d = $r->json();

        // Siempre que central las mande, se muestran las corridas recientes.
        if (is_array($d) && ! empty($d['recientes'])) {
            $this->line('Migraciones recientes de esta tienda:');
            $this->table(
                ['id', 'referencia', 'estado', 'TitanioPOS', 'enviados', 'fecha'],
                collect($d['recientes'])->map(fn ($m) => [
                    $m['id'], $m['referencia'], $m['estado'], $m['titanio'] ?? '—', $m['enviados'], $m['fecha'],
                ])->all()
            );
        }

        if (! $r->successful() || ! ($d['estado'] ?? false)) {
            $this->error('Central respondió: ' . ($d['msj'] ?? mb_substr($r->body(), 0, 300)));
            if (! empty($d['recientes'])) {
                $this->comment('Elige una de arriba:  php artisan inventario:migrar-errores --referencia=…');
            }
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Migración {$d['referencia']} · estado: {$d['estado_migracion']}"
            . ' · TitanioPOS: ' . ($d['titanio_status'] ?? '—'));
        $this->line('  productos enviados: ' . $d['total_enviado']);
        $this->line('  errores de TitanioPOS: ' . $d['errores_titanio']
            . ($d['truncados'] ? " (+{$d['truncados']} no guardados)" : ''));
        $this->line('  códigos de barras repetidos en el envío: ' . $d['codigos_repetidos']);

        if ($d['error_message'] ?? null) {
            $this->newLine();
            $this->warn('Motivo del aborto: ' . $d['error_message']);
        }

        $filas = $d['filas'] ?? [];
        if (! $filas) {
            $this->newLine();
            $this->info('No hay errores ni códigos repetidos: no se generó CSV.');
            return self::SUCCESS;
        }

        $ruta = $this->option('salida')
            ?: storage_path('app/migracion_errores_' . str_replace([':', '/', '\\'], '-', $d['referencia']) . '.csv');

        $fp = fopen($ruta, 'w');
        fwrite($fp, "\xEF\xBB\xBF");   // BOM: Excel respeta los acentos
        fputcsv($fp, [
            'origen', 'fila', 'id_producto', 'codigo_barras', 'codigo_proveedor',
            'descripcion', 'cantidad', 'precio_base', 'precio', 'mensaje',
        ], ';');

        foreach ($filas as $f) {
            fputcsv($fp, [
                $f['origen'], $f['fila'], $f['idinsucursal'], $f['codigo_barras'],
                $f['codigo_proveedor'], $f['descripcion'], $f['cantidad'],
                $f['precio_base'], $f['precio'], $f['mensaje'],
            ], ';');
        }
        fclose($fp);

        $this->newLine();
        $this->info("CSV generado: {$ruta}");
        $this->line('  filas: ' . count($filas));
        $this->newLine();
        $this->comment('Corrige los códigos repetidos en el inventario y vuelve a migrar con una referencia NUEVA,');
        $this->comment('o carga sin ellos con:  php artisan inventario:migrar --referencia=' . $d['referencia'] . ' --allow-partial');

        return self::SUCCESS;
    }
}
