// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');
const { assertPedidoFacturado, assertPagoExiste, TIPO } = require('./_support/strict-assertions');

const baseUrl = () => config.arabitofacturacion.baseUrl;

test.describe('Descuento + efectivo (STRICT — verificación del fix 2026-05-27)', () => {
    test('API: descuento 50% + efectivo total descontado → backend pide aprobación central UNA vez', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // 1) Pedido
        const respCrear = await page.request.get(baseUrl() + '/addNewPedido');
        const idPedido = await respCrear.json();

        // 2) Producto (match exacto por código_barras)
        const codigoBuscar = config.datos_prueba.producto_codigo_barras;
        const respBusqueda = await page.request.post(baseUrl() + '/getinventario', {
            data: { q: codigoBuscar, num: 50 },
        });
        const arr = await respBusqueda.json().catch(() => []);
        const lista = Array.isArray(arr) ? arr : (arr?.data || []);
        const producto = lista.find((p) => p.codigo_barras === codigoBuscar) || lista[0];
        expect(producto?.id, 'no se encontró producto de prueba').toBeTruthy();

        // 3) Agregar 1 unidad
        await page.request.get(baseUrl() + '/setCarrito', {
            params: { numero_factura: idPedido, id: producto.id, cantidad: 1 },
        });

        // 4) Cliente
        const respCli = await page.request.post(baseUrl() + '/getpersona', {
            data: { q: config.datos_prueba.cliente_identificacion },
        });
        const cliJson = await respCli.json();
        const cli = Array.isArray(cliJson) ? cliJson[0] : cliJson;
        if (cli?.id) {
            await page.request.post(baseUrl() + '/setpersonacarrito', {
                data: { numero_factura: idPedido, id_cliente: cli.id },
            });
        }

        // 5) Total real ANTES del descuento
        const respPed = await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } });
        const ped = await respPed.json();
        const totalReal = parseFloat(ped.clean_total);
        expect(totalReal, 'pedido sin total').toBeGreaterThan(0);

        // 6) Aplicar descuento 50%
        const respDesc = await page.request.post(baseUrl() + '/setDescuentoTotal', {
            data: { index: idPedido, descuento: 50 },
        });
        const bodyDesc = await respDesc.json();
        expect(bodyDesc.estado, `setDescuentoTotal falló: ${bodyDesc.msj}`).toBe(true);

        // 7) Total con descuento
        const ped2 = await (await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } })).json();
        const totalDesc = parseFloat(ped2.clean_total);
        expect(Math.abs(totalDesc - totalReal / 2), 'descuento 50% no aplicado correctamente').toBeLessThan(0.05);

        // 8) Facturar EFECTIVO por el total descontado
        const respFact = await page.request.post(baseUrl() + '/setPagoPedido', {
            data: { id: idPedido, efectivo: totalDesc },
        });
        const bodyFact = await respFact.json();

        // HARD: El fix de 2026-05-27 hace que se pida aprobación central INCLUSO con efectivo
        // Antes del fix, efectivo se procesaba directo sin solicitud
        const pidióAprobación = bodyFact.solicitud_descuento_pendiente === true
            || (typeof bodyFact.msj === 'string' && /solicitud|aprobaci[óo]n/i.test(bodyFact.msj));
        expect(
            pidióAprobación,
            `setPagoPedido NO pidió aprobación de descuento. Respuesta: ${JSON.stringify(bodyFact).slice(0, 300)}`
        ).toBe(true);

        // HARD: El pedido NO debe estar facturado (estado=0), porque está pendiente de aprobación
        const respPedFinal = await page.request.post(baseUrl() + '/testing/pedido-full', {
            data: { id_pedido: idPedido },
        });
        const dataFinal = await respPedFinal.json();
        expect(
            Number(dataFinal.pedido.estado),
            `Pedido facturado prematuramente sin aprobación central. estado=${dataFinal.pedido.estado}`
        ).toBe(0);

        await watcher.assertClean();
    });
});
