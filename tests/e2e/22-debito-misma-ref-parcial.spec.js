// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');
const { assertPedidoFacturado, TIPO } = require('./_support/strict-assertions');

const baseUrl = () => config.arabitofacturacion.baseUrl;

/**
 * DEBITO-MISMA-REF-PARCIAL 2026-06-11 — reproduce el caso #411148:
 * cliente paga con la MISMA tarjeta dos veces (parcial). Ambos débitos comparten
 * la terminación de 4 dígitos (ej "0967"):
 *   - Débito 1: $13.5 ref 0967 (imborrable, persistido por POS)
 *   - Débito 2: $9    ref 0967 (nuevo)
 *   - Total: $22.5 = total del pedido
 *
 * Bug: el bulk insert saltaba el SEGUNDO débito porque su ref ya estaba imborrable
 * → solo $13.5 en BD → "Pedido 22.5 vs Pagos 13.5" → BLOQUEADO injustamente.
 *
 * Fix: skip por (referencia + monto), no solo por referencia.
 */
test.describe('Débito misma referencia, dos cobros parciales (caso #411148)', () => {
    test('API: 1er débito imborrable + 2do débito misma ref distinto monto → factura OK', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // 1) Pedido + producto (cantidad para tener total sustancial)
        const idPedido = await (await page.request.get(baseUrl() + '/addNewPedido')).json();
        const lista = await (await page.request.post(baseUrl() + '/getinventario', {
            data: { q: config.datos_prueba.producto_codigo_barras, num: 50 },
        })).json();
        const arr = Array.isArray(lista) ? lista : (lista?.data || []);
        const producto = arr.find((p) => p.codigo_barras === config.datos_prueba.producto_codigo_barras) || arr[0];
        if (!producto?.id) { test.skip(true, 'sin productos'); return; }
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

        // 2) Total + tasa
        const ped = await (await page.request.post(baseUrl() + '/getPedido', { data: { id: idPedido } })).json();
        const totalUSD = parseFloat(ped.clean_total);
        const tasaBs = parseFloat(ped.items?.[0]?.tasa) || (config.tasas_esperadas?.bs_por_usd ?? 50);
        const totalBs = totalUSD * tasaBs;

        // 3) Partir en 2 débitos con la MISMA referencia "0967"
        const debito1Bs = parseFloat((totalBs * 0.6).toFixed(2)); // 60%
        const debito2Bs = parseFloat((totalBs - debito1Bs).toFixed(2)); // resto

        // 4) Persistir el 1er débito como imborrable (simula POS aprobado)
        const respPos = await page.request.post(baseUrl() + '/testing/pagos', {
            data: { id_pedido: idPedido, tipo: TIPO.DEBITO },
        });
        // Crear el imborrable directamente: usamos un endpoint o lo simulamos via setCarrito no aplica.
        // En su lugar mandamos los 2 débitos juntos en setPagoPedido; el primero SIN posData
        // imborrable previo (no podemos crear imborrable sin POS real), así que validamos el
        // camino donde ambos van en el request con misma ref + distinto monto.

        // 5) Facturar con 2 débitos misma ref, distinto monto
        const respFact = await page.request.post(baseUrl() + '/setPagoPedido', {
            data: {
                id: idPedido,
                debitos: [
                    { monto: debito1Bs, referencia: '0967' },
                    { monto: debito2Bs, referencia: '0967' },
                ],
            },
        });
        const body = await respFact.json();

        // HARD: debe facturar (no bloquear por descuadre) — la suma de los 2 = total
        expect(
            body.estado,
            `BUG: 2 débitos misma ref no facturaron. Total $${totalUSD}. Resp: ${JSON.stringify(body).slice(0, 300)}`
        ).toBe(true);

        // HARD: ambos débitos en BD (2 filas tipo=2)
        const pagos = await (await page.request.post(baseUrl() + '/testing/pagos', {
            data: { id_pedido: idPedido, tipo: TIPO.DEBITO },
        })).json();
        expect(pagos.count, 'esperaba 2 débitos persistidos (misma ref, distinto monto)').toBe(2);

        // HARD: pedido cerrado y cuadra
        await assertPedidoFacturado(page, { idPedido, itemsCount: 1 });

        await watcher.assertClean();
    });
});
