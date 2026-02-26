<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\Http\Controllers\tickera;

class ImprimirTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int|string ID del pedido a imprimir */
    protected $idPedido;

    /** @var array Opciones de impresión (moneda, printer, etc.) */
    protected $opciones;

    /** @var array Datos de sesión necesarios para tickera (usuario, id_usuario, nombre_usuario) */
    protected $sessionData;

    public function __construct($idPedido, array $opciones = [], array $sessionData = [])
    {
        $this->idPedido = $idPedido;
        $this->opciones = $opciones;
        $this->sessionData = $sessionData;
    }

    public function handle()
    {
        try {
            if (!empty($this->sessionData)) {
                foreach ($this->sessionData as $key => $value) {
                    session([$key => $value]);
                }
            }

            $printReq = Request::create('/internal', 'POST', array_merge([
                'id' => $this->idPedido,
                'moneda' => $this->opciones['moneda'] ?? 'bs',
                'printer' => $this->opciones['printer'] ?? null,
            ], $this->opciones));

            $tickera = new tickera();
            $resp = $tickera->imprimir($printReq);
            $data = $resp->getData(true);

            if (empty($data['estado'])) {
                \Log::warning('ImprimirTicketJob: impresión no exitosa', [
                    'id_pedido' => $this->idPedido,
                    'msj' => $data['msj'] ?? 'Sin mensaje',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('ImprimirTicketJob: ' . $e->getMessage(), [
                'id_pedido' => $this->idPedido,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
