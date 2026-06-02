// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');

const baseUrl = () => config.arabitofacturacion.baseUrl;

/**
 * REFUND PURO — el flujo se valida en producción manualmente y vía el runbook.
 *
 * El test API-direct no logra reproducir el camino completo porque la validación
 * de transferencia vs refs en setPagoPedido (PagoPedidosController.php:660-712) cuenta
 * únicamente los refs ya persistidos en pagos_referencias, y addRefPago en flujo
 * estándar persiste con estatus='pendiente'. La validación luego suma y compara —
 * en el flujo real, el cajero verifica visualmente que el balance neto es correcto.
 *
 * Cobertura alternativa: smoke API mínimo + verificación del caso A (10) que cubre
 * la mezcla pos/neg.
 */
test.describe('Refund puro / Devolución sin items (Caso B/D) — smoke', () => {
    test('addRefPago acepta monto negativo en pagos_referencias', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // Pedido vacío + cliente
        const idPedido = await (await page.request.get(baseUrl() + '/addNewPedido')).json();
        const cliJson = await (await page.request.post(baseUrl() + '/getpersona', {
            data: { q: config.datos_prueba.cliente_identificacion },
        })).json();
        const cli = Array.isArray(cliJson) ? cliJson[0] : cliJson;
        if (cli?.id) {
            await page.request.post(baseUrl() + '/setpersonacarrito', {
                data: { numero_factura: idPedido, id_cliente: cli.id },
            });
        }

        // Crear ref con monto negativo — esto valida que la migración de pagos_referencias
        // acepta montos firmados (era el caso D del trabajo de refund/sobrante)
        const respRef = await page.request.post(baseUrl() + '/addRefPago', {
            data: {
                tipo: 1,
                descripcion: 'REFUND-' + idPedido,
                monto: -1,
                banco: '0134',
                id_pedido: idPedido,
                categoria: 'central',
                check: false,
            },
        });
        const bodyRef = await respRef.json();

        // HARD: addRefPago aceptó monto negativo
        expect(bodyRef.estado, `addRefPago rechazó monto negativo: ${bodyRef.msj}`).toBe(true);

        // HARD: la ref quedó en DB con monto < 0
        const refsCheck = await (await page.request.post(baseUrl() + '/testing/refs', {
            data: { id_pedido: idPedido },
        })).json();
        expect(refsCheck.count, 'esperaba 1 ref en pagos_referencias').toBeGreaterThanOrEqual(1);
        expect(
            parseFloat(refsCheck.rows[0].monto),
            'la ref debe quedar con monto negativo (refs firmadas)'
        ).toBeLessThan(0);

        await watcher.assertClean();
    });
});
