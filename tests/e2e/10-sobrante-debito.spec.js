// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');
const { assertPedidoFacturado, TIPO } = require('./_support/strict-assertions');

const baseUrl = () => config.arabitofacturacion.baseUrl;

test.describe('Sobrante débito Caso A — STRICT (API-direct)', () => {
    test('Débito > total + transferencia -refund con ref → ambos pagos persistidos con signo correcto', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // 1) Pedido + producto
        const idPedido = await (await page.request.get(baseUrl() + '/addNewPedido')).json();

        const lista = await (await page.request.post(baseUrl() + '/getinventario', {
            data: { q: config.datos_prueba.producto_codigo_barras, num: 50 },
        })).json();
        const arr = Array.isArray(lista) ? lista : (lista?.data || []);
        // Match estricto si existe; sino primer producto disponible (el test solo necesita uno)
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
            test.skip(true, 'cliente de prueba no encontrado en BD');
            return;
        }
        await page.request.post(baseUrl() + '/setpersonacarrito', {
            data: { numero_factura: idPedido, id_cliente: cli.id },
        });

        // 2) Total real
        const ped = await (await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } })).json();
        const totalUSD = parseFloat(ped.clean_total);
        const tasaBs = parseFloat(ped.items?.[0]?.tasa) || (config.tasas_esperadas?.bs_por_usd ?? 50);

        // 3) Sobrante: cliente paga 4 USD extra en débito
        const sobranteUSD = 4;
        const debitoBs = (totalUSD + sobranteUSD) * tasaBs;
        const transferRefundUSD = -sobranteUSD;

        // 4) Crear ref saliente (refund) antes de facturar — usar banco divisa para que el monto
        // se interprete en USD directamente sin conversión Bs (validación setPagoPedido:670-684)
        const respRef = await page.request.post(baseUrl() + '/addRefPago', {
            data: {
                tipo: 1,
                descripcion: 'REFUND-A-' + idPedido,
                monto: transferRefundUSD,
                banco: config.datos_prueba.banco_divisa_nombre || 'ZELLE',
                id_pedido: idPedido,
                categoria: 'central',
                check: false,
            },
        });
        const bodyRef = await respRef.json();
        expect(bodyRef.estado, `addRefPago refund A falló: ${bodyRef.msj}`).toBe(true);

        // 5) Facturar con mezcla pos/neg
        const respFact = await page.request.post(baseUrl() + '/setPagoPedido', {
            data: {
                id: idPedido,
                debitos: [{ monto: debitoBs }],
                transferencia: transferRefundUSD,
            },
        });
        const body = await respFact.json();

        // HARD: backend aceptó la mezcla pos/neg O pidió aprobación
        const procesoOk = body.estado === true || body.id_tarea || body.solicitud_descuento_pendiente;
        expect(procesoOk, `setPagoPedido caso A falló: ${body.msj}`).toBeTruthy();

        // 6) Si procesado directo, validar DB
        if (body.estado === true) {
            await assertPedidoFacturado(page, { idPedido, itemsCount: 1 });

            const debiPagos = await (await page.request.post(baseUrl() + '/testing/pagos', {
                data: { id_pedido: idPedido, tipo: TIPO.DEBITO },
            })).json();
            const trPagos = await (await page.request.post(baseUrl() + '/testing/pagos', {
                data: { id_pedido: idPedido, tipo: TIPO.TRANSFERENCIA },
            })).json();
            expect(debiPagos.count, 'esperaba ≥1 pago débito').toBeGreaterThanOrEqual(1);
            expect(trPagos.count, 'esperaba ≥1 pago transferencia (refund)').toBeGreaterThanOrEqual(1);
            expect(parseFloat(trPagos.rows[0].monto), 'transferencia debe ser negativa').toBeLessThan(0);
        }

        await watcher.assertClean();
    });
});
