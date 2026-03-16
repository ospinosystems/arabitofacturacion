<?php

namespace App\Jobs;

use App\Models\pedidos;
use App\Services\CuadrePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class CuadreDiarioDescargaMasivaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $descargaId;
    public int $timeout = 0;
    public int $tries = 1;

    public function __construct(int $descargaId)
    {
        $this->descargaId = $descargaId;
    }

    protected function dateExpr(): string
    {
        return 'DATE(COALESCE(pedidos.fecha_factura, pedidos.created_at))';
    }

    public function handle(): void
    {
        set_time_limit(0);
        $row = DB::table('cuadre_descargas')->where('id', $this->descargaId)->first();
        if (!$row || $row->status !== 'pending') {
            return;
        }

        DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
            'status'     => 'processing',
            'started_at' => now(),
            'total_fechas' => 0,
            'fechas_procesadas' => 0,
            'total_pedidos' => 0,
        ]);
        $fechas = json_decode($row->fechas, true);
        if (!is_array($fechas) || empty($fechas)) {
            DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
                'status' => 'failed',
                'error_message' => 'No hay fechas.',
            ]);
            return;
        }

        DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
            'total_fechas' => count($fechas),
        ]);

        $dateExpr = $this->dateExpr();
        $dirName = 'descargas_cuadre';
        $dirFull = storage_path('app/' . $dirName);
        if (!is_dir($dirFull)) {
            if (!@mkdir($dirFull, 0755, true)) {
                throw new \RuntimeException('No se pudo crear el directorio: ' . $dirFull);
            }
        }
        $tempZip = $dirFull . '/' . $row->token . '.zip';

        try {
            if (!class_exists('ZipArchive')) {
                throw new \RuntimeException('La extensión PHP ZipArchive no está instalada.');
            }
            $zip = new ZipArchive();
            if ($zip->open($tempZip, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('No se pudo crear el archivo ZIP en: ' . $tempZip);
            }

            $totalPedidos = 0;
            $fechasProcesadas = 0;
            foreach ($fechas as $fecha) {
                DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
                    'fecha_actual' => $fecha,
                ]);

                $pedidosList = pedidos::whereRaw($dateExpr . ' = ?', [$fecha])
                    ->where('pedidos.valido', true)
                    ->whereNotNull('pedidos.numero_factura')
                    ->orderBy('pedidos.numero_factura')
                    ->orderBy('pedidos.id')
                    ->get();

                $carpetaMes = date('Y-m', strtotime($fecha));
                $prefijo = $carpetaMes . '/' . $fecha . '/';

                foreach ($pedidosList as $pedido) {
                    $pdfContent = CuadrePdfService::generarPdfPedido($pedido->id);
                    if ($pdfContent === null) {
                        continue;
                    }
                    $nombre = $pedido->numero_factura ? 'factura-' . $pedido->numero_factura : 'pedido-' . $pedido->id;
                    $zip->addFromString($prefijo . $nombre . '.pdf', $pdfContent);
                    $totalPedidos++;
                    unset($pdfContent);
                    if ($totalPedidos % 500 === 0) {
                        gc_collect_cycles();
                    }
                }

                $fechasProcesadas++;
                DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
                    'fechas_procesadas' => $fechasProcesadas,
                    'total_pedidos'     => $totalPedidos,
                ]);
            }

            $zip->close();

            $pathRel = $dirName . '/' . $row->token . '.zip';
            DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
                'status'   => 'ready',
                'path'     => $pathRel,
                'ready_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if (isset($tempZip) && file_exists($tempZip)) {
                @unlink($tempZip);
            }
            $message = $e->getMessage();
            \Log::error('CuadreDiarioDescargaMasivaJob failed: ' . $message, [
                'descarga_id' => $this->descargaId,
                'exception'   => (string) $e,
            ]);
            DB::table('cuadre_descargas')->where('id', $this->descargaId)->update([
                'status'        => 'failed',
                'error_message' => $message,
            ]);
            throw $e;
        }
    }
}
