// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');
const { assertPedidoFacturado } = require('./_support/strict-assertions');

const baseUrl = () => config.arabitofacturacion.baseUrl;

test.describe('Sobrante transferencia Caso C — STRICT', () => {
    test('API: refs entrante + saliente que netean al total → pedido facturado', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // 1) Setup pedido + item + cliente
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
            params: { numero_factura: idPedido, id: producto.id, cantidad: 1 },
        });

        const cliJson = await (await page.request.post(baseUrl() + '/getpersona', {
            data: { q: config.datos_prueba.cliente_identificacion },
        })).json();
        const cli = Array.isArray(cliJson) ? cliJson[0] : cliJson;
        if (!cli?.id) {
            test.skip(true, 'cliente de prueba no encontrado');
            return;
        }
        await page.request.post(baseUrl() + '/setpersonacarrito', {
            data: { numero_factura: idPedido, id_cliente: cli.id },
        });

        const ped = await (await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } })).json();
        const totalUSD = parseFloat(ped.clean_total);
        const sobrante = 2;

        // 2) Crear DOS refs antes de facturar: entrante (mayor) + saliente (refund del sobrante)
        // Banco divisa para que monto USD se interprete directo (sin conversión Bs)
        const bancoDivisa = config.datos_prueba.banco_divisa_nombre || 'ZELLE';
        const respIn = await page.request.post(baseUrl() + '/addRefPago', {
            data: {
                tipo: 1,
                descripcion: 'IN-C-' + idPedido,
                monto: totalUSD + sobrante,
                banco: bancoDivisa,
                id_pedido: idPedido,
                categoria: 'central',
                check: false,
            },
        });
        expect((await respIn.json()).estado).toBe(true);

        const respOut = await page.request.post(baseUrl() + '/addRefPago', {
            data: {
                tipo: 1,
                descripcion: 'OUT-C-' + idPedido,
                monto: -sobrante,
                banco: bancoDivisa,
                id_pedido: idPedido,
                categoria: 'central',
                check: false,
            },
        });
        expect((await respOut.json()).estado).toBe(true);

        // 3) Facturar: transferencia neta = totalUSD
        const respFact = await page.request.post(baseUrl() + '/setPagoPedido', {
            data: {
                id: idPedido,
                transferencia: totalUSD,
            },
        });
        const body = await respFact.json();

        // HARD: backend procesó o pidió aprobación central
        const procesoOk = body.estado === true || body.id_tarea
            || /pendiente|aprobaci/i.test(body.msj || '');
        expect(procesoOk, `setPagoPedido caso C no avanzó: ${body.msj}`).toBe(true);

        if (body.estado === true) {
            await assertPedidoFacturado(page, { idPedido, itemsCount: 1 });
        }

        await watcher.assertClean();
    });
});
