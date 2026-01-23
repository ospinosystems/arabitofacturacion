<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\pedidos;
use App\Models\items_pedidos;
use App\Models\pago_pedidos;
use App\Models\clientes;
use App\Models\usuarios;
use App\Models\inventarios;
use Carbon\Carbon;

class CierreTestSeeder extends Seeder
{
    /**
     * Seeder para probar el proceso de cierre con todos los métodos de pago y monedas
     * 
     * MÉTODOS DE PAGO:
     * - Tipo 1: Transferencia
     * - Tipo 2: Débito (Pinpad)
     * - Tipo 3: Efectivo (Dólar, Bs, COP)
     * - Tipo 4: Crédito
     * - Tipo 5: Otros
     * 
     * MONEDAS:
     * - dolar (USD)
     * - bs (Bolívares)
     * - peso (COP - Pesos Colombianos)
     * - euro (EUR)
     */
    public function run()
    {
        // Obtener o crear cliente de prueba
        $cliente = clientes::firstOrCreate(
            ['cedula' => '99999999'],
            [
                'nombre' => 'Cliente Prueba Cierre',
                'telefono' => '04241234567',
                'direccion' => 'Dirección de prueba',
                'correo' => 'prueba@cierre.com'
            ]
        );

        // Obtener primer usuario disponible
        $usuario = usuarios::first();
        if (!$usuario) {
            throw new \Exception('No hay usuarios en la base de datos. Ejecuta primero el seeder de usuarios.');
        }

        // Obtener productos del inventario
        $productos = inventarios::take(5)->get();
        if ($productos->count() < 5) {
            throw new \Exception('Se necesitan al menos 5 productos en el inventario. Ejecuta primero el seeder de inventarios.');
        }

        $fechaHoy = Carbon::now();

        // ============================================
        // PEDIDO 1: EFECTIVO EN DÓLARES
        // ============================================
        $pedido1 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido1->id,
            'id_producto' => $productos[0]->id,
            'cantidad' => 2,
            'descuento' => 0,
            'monto' => 50.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        pago_pedidos::create([
            'id_pedido' => $pedido1->id,
            'tipo' => 3, // Efectivo
            'cuenta' => 1,
            'monto' => 50.00,
            'monto_original' => 50.00,
            'moneda' => 'dolar'
        ]);

        // ============================================
        // PEDIDO 2: EFECTIVO EN BOLÍVARES
        // ============================================
        $pedido2 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido2->id,
            'id_producto' => $productos[1]->id,
            'cantidad' => 1,
            'descuento' => 0,
            'monto' => 30.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Efectivo en Bs (asumiendo tasa de 45 Bs/$)
        pago_pedidos::create([
            'id_pedido' => $pedido2->id,
            'tipo' => 3, // Efectivo
            'cuenta' => 1,
            'monto' => 30.00, // USD
            'monto_original' => 1350.00, // 30 * 45 = 1350 Bs
            'moneda' => 'bs'
        ]);

        // ============================================
        // PEDIDO 3: EFECTIVO EN PESOS COLOMBIANOS
        // ============================================
        $pedido3 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido3->id,
            'id_producto' => $productos[2]->id,
            'cantidad' => 3,
            'descuento' => 0,
            'monto' => 75.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Efectivo en COP (asumiendo tasa de 4200 COP/$)
        pago_pedidos::create([
            'id_pedido' => $pedido3->id,
            'tipo' => 3, // Efectivo
            'cuenta' => 1,
            'monto' => 75.00, // USD
            'monto_original' => 315000.00, // 75 * 4200 = 315,000 COP
            'moneda' => 'peso'
        ]);

        // ============================================
        // PEDIDO 4: DÉBITO (PINPAD) - TERMINAL 1
        // ============================================
        $pedido4 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido4->id,
            'id_producto' => $productos[3]->id,
            'cantidad' => 1,
            'descuento' => 0,
            'monto' => 100.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Débito con datos POS (Terminal 1, Lote 123)
        // pos_amount viene multiplicado por 100 (4500.00 Bs * 100 = 450000)
        pago_pedidos::create([
            'id_pedido' => $pedido4->id,
            'tipo' => 2, // Débito
            'cuenta' => 1,
            'monto' => 100.00, // USD
            'monto_original' => 4500.00, // 100 * 45 = 4500 Bs
            'moneda' => 'bs',
            'referencia' => '1234', // Últimos 4 dígitos de la tarjeta
            'pos_message' => 'APROBADO',
            'pos_lote' => 123,
            'pos_responsecode' => '00',
            'pos_amount' => 450000, // 4500.00 * 100
            'pos_terminal' => 'TERM001',
            'pos_json_response' => json_encode([
                'message' => 'APROBADO',
                'reference' => '123456789',
                'ordernumber' => $pedido4->id,
                'sequence' => '001',
                'approval' => 'APP123',
                'lote' => 123,
                'responsecode' => '00',
                'datetime' => $fechaHoy->format('Y-m-d H:i:s'),
                'amount' => 450000,
                'terminal' => 'TERM001'
            ])
        ]);

        // ============================================
        // PEDIDO 5: DÉBITO (PINPAD) - TERMINAL 1 (mismo lote)
        // ============================================
        $pedido5 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido5->id,
            'id_producto' => $productos[4]->id,
            'cantidad' => 2,
            'descuento' => 0,
            'monto' => 80.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Otro débito en la misma terminal y lote
        pago_pedidos::create([
            'id_pedido' => $pedido5->id,
            'tipo' => 2, // Débito
            'cuenta' => 1,
            'monto' => 80.00, // USD
            'monto_original' => 3600.00, // 80 * 45 = 3600 Bs
            'moneda' => 'bs',
            'referencia' => '5678',
            'pos_message' => 'APROBADO',
            'pos_lote' => 123, // Mismo lote que pedido4
            'pos_responsecode' => '00',
            'pos_amount' => 360000, // 3600.00 * 100
            'pos_terminal' => 'TERM001', // Misma terminal
            'pos_json_response' => json_encode([
                'message' => 'APROBADO',
                'reference' => '987654321',
                'ordernumber' => $pedido5->id,
                'sequence' => '002',
                'approval' => 'APP456',
                'lote' => 123,
                'responsecode' => '00',
                'datetime' => $fechaHoy->format('Y-m-d H:i:s'),
                'amount' => 360000,
                'terminal' => 'TERM001'
            ])
        ]);

        // ============================================
        // PEDIDO 6: DÉBITO (PINPAD) - TERMINAL 2
        // ============================================
        $pedido6 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido6->id,
            'id_producto' => $productos[0]->id,
            'cantidad' => 1,
            'descuento' => 0,
            'monto' => 60.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Débito en otra terminal (Terminal 2, Lote 456)
        pago_pedidos::create([
            'id_pedido' => $pedido6->id,
            'tipo' => 2, // Débito
            'cuenta' => 1,
            'monto' => 60.00, // USD
            'monto_original' => 2700.00, // 60 * 45 = 2700 Bs
            'moneda' => 'bs',
            'referencia' => '9012',
            'pos_message' => 'APROBADO',
            'pos_lote' => 456,
            'pos_responsecode' => '00',
            'pos_amount' => 270000, // 2700.00 * 100
            'pos_terminal' => 'TERM002',
            'pos_json_response' => json_encode([
                'message' => 'APROBADO',
                'reference' => '555666777',
                'ordernumber' => $pedido6->id,
                'sequence' => '001',
                'approval' => 'APP789',
                'lote' => 456,
                'responsecode' => '00',
                'datetime' => $fechaHoy->format('Y-m-d H:i:s'),
                'amount' => 270000,
                'terminal' => 'TERM002'
            ])
        ]);

        // ============================================
        // PEDIDO 7: TRANSFERENCIA
        // ============================================
        $pedido7 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido7->id,
            'id_producto' => $productos[1]->id,
            'cantidad' => 2,
            'descuento' => 0,
            'monto' => 120.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        pago_pedidos::create([
            'id_pedido' => $pedido7->id,
            'tipo' => 1, // Transferencia
            'cuenta' => 1,
            'monto' => 120.00,
            'monto_original' => 120.00,
            'moneda' => 'dolar',
            'referencia' => 'TRANS123456'
        ]);

        // ============================================
        // PEDIDO 8: CRÉDITO
        // ============================================
        $pedido8 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido8->id,
            'id_producto' => $productos[2]->id,
            'cantidad' => 1,
            'descuento' => 0,
            'monto' => 200.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        pago_pedidos::create([
            'id_pedido' => $pedido8->id,
            'tipo' => 4, // Crédito
            'cuenta' => 1,
            'monto' => 200.00,
            'monto_original' => 200.00,
            'moneda' => 'dolar'
        ]);

        // ============================================
        // PEDIDO 9: PAGO MIXTO (Efectivo USD + Efectivo Bs)
        // ============================================
        $pedido9 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido9->id,
            'id_producto' => $productos[3]->id,
            'cantidad' => 1,
            'descuento' => 0,
            'monto' => 150.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Efectivo en dólares
        pago_pedidos::create([
            'id_pedido' => $pedido9->id,
            'tipo' => 3,
            'cuenta' => 1,
            'monto' => 100.00,
            'monto_original' => 100.00,
            'moneda' => 'dolar'
        ]);

        // Efectivo en bolívares
        pago_pedidos::create([
            'id_pedido' => $pedido9->id,
            'tipo' => 3,
            'cuenta' => 1,
            'monto' => 50.00, // USD
            'monto_original' => 2250.00, // 50 * 45 = 2250 Bs
            'moneda' => 'bs'
        ]);

        // ============================================
        // PEDIDO 10: PAGO MIXTO (Efectivo COP + Débito)
        // ============================================
        $pedido10 = pedidos::create([
            'estado' => 1,
            'export' => 0,
            'fecha_inicio' => $fechaHoy,
            'fecha_vence' => $fechaHoy->copy()->addDays(30),
            'formato_pago' => 1,
            'ticked' => 0,
            'fiscal' => 0,
            'retencion' => 0,
            'id_cliente' => $cliente->id,
            'id_vendedor' => $usuario->id,
            'fecha_factura' => $fechaHoy
        ]);

        items_pedidos::create([
            'id_pedido' => $pedido10->id,
            'id_producto' => $productos[4]->id,
            'cantidad' => 2,
            'descuento' => 0,
            'monto' => 180.00,
            'entregado' => 1,
            'condicion' => 0
        ]);

        // Efectivo en pesos
        pago_pedidos::create([
            'id_pedido' => $pedido10->id,
            'tipo' => 3,
            'cuenta' => 1,
            'monto' => 90.00, // USD
            'monto_original' => 378000.00, // 90 * 4200 = 378,000 COP
            'moneda' => 'peso'
        ]);

        // Débito (Terminal 1, mismo lote 123)
        pago_pedidos::create([
            'id_pedido' => $pedido10->id,
            'tipo' => 2,
            'cuenta' => 1,
            'monto' => 90.00, // USD
            'monto_original' => 4050.00, // 90 * 45 = 4050 Bs
            'moneda' => 'bs',
            'referencia' => '3456',
            'pos_message' => 'APROBADO',
            'pos_lote' => 123, // Mismo lote que pedidos 4 y 5
            'pos_responsecode' => '00',
            'pos_amount' => 405000, // 4050.00 * 100
            'pos_terminal' => 'TERM001',
            'pos_json_response' => json_encode([
                'message' => 'APROBADO',
                'reference' => '111222333',
                'ordernumber' => $pedido10->id,
                'sequence' => '003',
                'approval' => 'APP999',
                'lote' => 123,
                'responsecode' => '00',
                'datetime' => $fechaHoy->format('Y-m-d H:i:s'),
                'amount' => 405000,
                'terminal' => 'TERM001'
            ])
        ]);

        echo "\n✅ Seeder ejecutado exitosamente!\n\n";
        echo "==========================================\n";
        echo "RESUMEN DE DATOS CREADOS\n";
        echo "==========================================\n\n";
        
        echo "📦 Total de pedidos creados: 10\n\n";
        
        echo "💵 EFECTIVO DÓLAR:\n";
        echo "   - Pedido 1: \$50.00\n";
        echo "   - Pedido 9: \$100.00\n";
        echo "   TOTAL: \$150.00 USD\n\n";
        
        echo "💵 EFECTIVO BOLÍVARES:\n";
        echo "   - Pedido 2: 1,350.00 Bs (\$30.00 USD)\n";
        echo "   - Pedido 9: 2,250.00 Bs (\$50.00 USD)\n";
        echo "   TOTAL: 3,600.00 Bs (\$80.00 USD)\n\n";
        
        echo "💵 EFECTIVO PESOS COLOMBIANOS:\n";
        echo "   - Pedido 3: 315,000.00 COP (\$75.00 USD)\n";
        echo "   - Pedido 10: 378,000.00 COP (\$90.00 USD)\n";
        echo "   TOTAL: 693,000.00 COP (\$165.00 USD)\n\n";
        
        echo "💳 DÉBITO (PINPAD):\n";
        echo "   TERMINAL 1 (TERM001) - LOTE 123:\n";
        echo "     - Pedido 4: 4,500.00 Bs (\$100.00 USD)\n";
        echo "     - Pedido 5: 3,600.00 Bs (\$80.00 USD)\n";
        echo "     - Pedido 10: 4,050.00 Bs (\$90.00 USD)\n";
        echo "     Subtotal: 12,150.00 Bs (\$270.00 USD) - 3 transacciones\n\n";
        echo "   TERMINAL 2 (TERM002) - LOTE 456:\n";
        echo "     - Pedido 6: 2,700.00 Bs (\$60.00 USD)\n";
        echo "     Subtotal: 2,700.00 Bs (\$60.00 USD) - 1 transacción\n\n";
        echo "   TOTAL DÉBITO: 14,850.00 Bs (\$330.00 USD) - 4 transacciones\n\n";
        
        echo "🏦 TRANSFERENCIA:\n";
        echo "   - Pedido 7: \$120.00 USD\n\n";
        
        echo "📋 CRÉDITO:\n";
        echo "   - Pedido 8: \$200.00 USD\n\n";
        
        echo "==========================================\n";
        echo "VALORES ESPERADOS EN CAJA\n";
        echo "==========================================\n\n";
        
        echo "🔢 EFECTIVO QUE DEBES TENER EN CAJA:\n";
        echo "   • Dólares: \$150.00 USD\n";
        echo "   • Bolívares: 3,600.00 Bs\n";
        echo "   • Pesos Colombianos: 693,000.00 COP\n\n";
        
        echo "🔢 LOTES PINPAD:\n";
        echo "   • TERM001 - Lote 123:\n";
        echo "     Monto: 12,150.00 Bs (\$270.00 USD)\n";
        echo "     Transacciones: 3\n";
        echo "     pos_amount total: 1,215,000 (12,150.00 * 100)\n\n";
        echo "   • TERM002 - Lote 456:\n";
        echo "     Monto: 2,700.00 Bs (\$60.00 USD)\n";
        echo "     Transacciones: 1\n";
        echo "     pos_amount total: 270,000 (2,700.00 * 100)\n\n";
        
        echo "📊 TOTAL GENERAL EN USD: \$1,015.00\n";
        echo "   (Efectivo: \$395.00 + Débito: \$330.00 + Transferencia: \$120.00 + Crédito: \$200.00)\n\n";
        
        echo "==========================================\n";
        echo "NOTAS IMPORTANTES\n";
        echo "==========================================\n\n";
        echo "⚠️  TASAS UTILIZADAS:\n";
        echo "   • 1 USD = 45 Bs\n";
        echo "   • 1 USD = 4,200 COP\n\n";
        echo "⚠️  FORMATO PINPAD:\n";
        echo "   • pos_amount: Monto en Bs multiplicado por 100\n";
        echo "   • pos_lote: Número de lote del terminal\n";
        echo "   • pos_terminal: Identificador del terminal\n";
        echo "   • pos_responsecode: '00' = APROBADO\n";
        echo "   • referencia: Últimos 4 dígitos de la tarjeta\n\n";
        echo "✅ Puedes ejecutar el cierre y verificar que los montos coincidan!\n\n";
    }
}
