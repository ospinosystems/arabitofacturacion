// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');

const baseUrl = () => config.arabitofacturacion.baseUrl;

/**
 * DESCUADRE-BLOQUEO 2026-06-11 — reproduce el caso #343756:
 * pedido que vale ~$49 cerrado con solo ~$26 cobrado (descuadre de $22).
 *
 * El bug: validarCuadrePedido leía $pedido->clean_total que es NULL (no es
 * columna de BD, es atributo calculado en getPedidoFun) → total=0 → cuadraba
 * siempre. Resultado: el sistema permitía cerrar pedidos cobrando de menos.
 *
 * Este test crea un pedido real con varios items y trata de facturarlo con
 * efectivo INSUFICIENTE. Debe REBOTAR con "montos no coinciden" / descuadre.
 */
test.describe('Descuadre — BLOQUEO de cierre con pago insuficiente', () => {
    test('API: pedido con items, pago efectivo < total → REBOTA (no cierra)', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // 1) Pedido + varios items para tener un total >0
        const idPedido = await (await page.request.get(baseUrl() + '/addNewPedido')).json();

        const lista = await (await page.request.post(baseUrl() + '/getinventario', {
            data: { q: config.datos_prueba.producto_codigo_barras, num: 50 },
        })).json();
        const arr = Array.isArray(lista) ? lista : (lista?.data || []);
        const producto = arr.find((p) => p.codigo_barras === config.datos_prueba.producto_codigo_barras) || arr[0];
        if (!producto?.id) {
            test.skip(true, 'sin productos en inventario');
            return;
        }
        // Cargar cantidad 3 para que el total sea sustancial
        await page.request.get(baseUrl() + '/setCarrito', {
            params: { numero_factura: idPedido, id: producto.id, cantidad: 3 },
        });

        const cliJson = await (await page.request.post(baseUrl() + '/getpersona', {
            data: { q: config.datos_prueba.cliente_identificacion },
        })).json();
        const cli = Array.isArray(cliJson) ? cliJson[0] : cliJson;
        if (cli?.id) {
            await page.request.post(baseUrl() + '/setpersonacarrito', {
                data: { numero_factura: idPedido, id_cliente: cli.id },
            });
        }

        // 2) Total real del pedido
        const ped = await (await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } })).json();
        const totalUSD = parseFloat(ped.clean_total);
        expect(totalUSD, 'pedido debe tener total > 0').toBeGreaterThan(0.01);

        // 3) Intentar facturar con efectivo INSUFICIENTE (la mitad del total)
        const pagoInsuficiente = parseFloat((totalUSD / 2).toFixed(2));
        const respFact = await page.request.post(baseUrl() + '/setPagoPedido', {
            data: { id: idPedido, efectivo: pagoInsuficiente },
        });
        const body = await respFact.json();

        // HARD: el backend DEBE rechazar — pago insuficiente NO puede cerrar el pedido
        expect(
            body.estado,
            `BUG GRAVE: el sistema aceptó cerrar pedido #${idPedido} (total $${totalUSD}) con solo $${pagoInsuficiente}. Respuesta: ${JSON.stringify(body).slice(0, 300)}`
        ).not.toBe(true);

        // HARD: el pedido NO debe quedar cerrado (estado=1)
        const dataFinal = await (await page.request.post(baseUrl() + '/testing/pedido-full', {
            data: { id_pedido: idPedido },
        })).json();
        expect(
            Number(dataFinal.pedido.estado),
            `BUG GRAVE: pedido #${idPedido} quedó cerrado (estado=1) con pago insuficiente`
        ).toBe(0);

        await watcher.assertClean();
    });

    test('API: pago EXACTO → SÍ cierra correctamente', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        const idPedido = await (await page.request.get(baseUrl() + '/addNewPedido')).json();
        const lista = await (await page.request.post(baseUrl() + '/getinventario', {
            data: { q: config.datos_prueba.producto_codigo_barras, num: 50 },
        })).json();
        const arr = Array.isArray(lista) ? lista : (lista?.data || []);
        const producto = arr.find((p) => p.codigo_barras === config.datos_prueba.producto_codigo_barras) || arr[0];
        if (!producto?.id) {
            test.skip(true, 'sin productos en inventario');
            return;
        }
        await page.request.get(baseUrl() + '/setCarrito', {
            params: { numero_factura: idPedido, id: producto.id, cantidad: 2 },
        });
        const cliJson = await (await page.request.post(baseUrl() + '/getpersona', {
            data: { q: config.datos_prueba.cliente_identificacion },
        })).json();
        const cli = Array.isArray(cliJson) ? cliJson[0] : cliJson;
        if (cli?.id) {
            await page.request.post(baseUrl() + '/setpersonacarrito', {
                data: { numero_factura: idPedido, id_cliente: cli.id },
            });
        }

        const ped = await (await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } })).json();
        const totalUSD = parseFloat(ped.clean_total);

        // Pago EXACTO en efectivo USD
        const respFact = await page.request.post(baseUrl() + '/setPagoPedido', {
            data: { id: idPedido, efectivo: totalUSD },
        });
        const body = await respFact.json();

        // HARD: pago exacto SÍ cierra
        expect(body.estado, `pago exacto debería cerrar: ${body.msj}`).toBe(true);

        const dataFinal = await (await page.request.post(baseUrl() + '/testing/pedido-full', {
            data: { id_pedido: idPedido },
        })).json();
        expect(Number(dataFinal.pedido.estado), 'pago exacto debe dejar estado=1').toBe(1);

        await watcher.assertClean();
    });
});
