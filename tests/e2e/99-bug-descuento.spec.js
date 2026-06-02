// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');

test.describe('BUG descuento + efectivo (diagnóstico)', () => {
    test('Reproducir el caso real con valores correctos', async ({ page }) => {
        await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        const baseUrl = config.arabitofacturacion.baseUrl;

        // 1) Pedido nuevo
        const respCrear = await page.request.get(baseUrl + '/addNewPedido');
        const idPedido = await respCrear.json();
        console.log('[1] Pedido creado:', idPedido);

        // 2) Producto — busca por codigo_barras y filtra match EXACTO
        const codigoBuscar = config.datos_prueba.producto_codigo_barras;
        const respBusqueda = await page.request.post(baseUrl + '/getinventario', {
            data: { q: codigoBuscar, num: 50 },
        });
        const arr = await respBusqueda.json().catch(() => []);
        const lista = Array.isArray(arr) ? arr : (arr?.data || []);
        const producto = lista.find(p => p.codigo_barras === codigoBuscar) || lista[0];
        console.log('[2] Producto:', producto?.id, producto?.descripcion, 'codigo=', producto?.codigo_barras, 'precio=', producto?.precio);
        const idProducto = producto?.id;
        expect(idProducto).toBeTruthy();

        // 3) Agregar 1 unidad
        await page.request.get(baseUrl + '/setCarrito', {
            params: { numero_factura: idPedido, id: idProducto, cantidad: 1 },
        });

        // 4) Cliente
        const respCli = await page.request.post(baseUrl + '/getpersona', {
            data: { q: config.datos_prueba.cliente_identificacion },
        });
        const cli = (await respCli.json())[0];
        console.log('[4] Cliente:', cli?.id);
        await page.request.post(baseUrl + '/setpersonacarrito', {
            data: { numero_factura: idPedido, id_cliente: cli.id },
        });

        // 5) LEER el pedido para saber el total real
        const respPed = await page.request.post(baseUrl + '/getPedido', { data: { id: idPedido } });
        const ped = await respPed.json();
        const totalReal = parseFloat(ped.clean_total);
        console.log('[5] Pedido total (clean_total):', totalReal, 'USD');
        console.log('    Items:', ped.items?.length);
        if (ped.items?.length) {
            for (const it of ped.items) {
                console.log('      item:', it.id, 'cant=', it.cantidad, 'precio_unit=', it.producto?.precio, 'monto=', it.monto, 'descuento=', it.descuento);
            }
        }

        // 6) Aplicar descuento 50%
        const respDesc = await page.request.post(baseUrl + '/setDescuentoTotal', {
            data: { index: idPedido, descuento: 50 },
        });
        const bodyDesc = await respDesc.json();
        console.log('[6] setDescuentoTotal:', JSON.stringify(bodyDesc));

        // 7) Releer total con descuento aplicado
        const ped2 = await (await page.request.post(baseUrl + '/getPedido', { data: { id: idPedido } })).json();
        const totalDesc = parseFloat(ped2.clean_total);
        console.log('[7] Total con descuento:', totalDesc, '(antes era', totalReal, ')');

        // 8) FACTURAR con efectivo USD que coincide con el total descontado
        const respFact = await page.request.post(baseUrl + '/setPagoPedido', {
            data: { id: idPedido, efectivo: totalDesc },
        });
        const bodyFact = await respFact.json();
        console.log('\n========== FACTURAR CON DESCUENTO ==========');
        console.log('  estado:', bodyFact.estado);
        console.log('  msj:', bodyFact.msj);
        console.log('  solicitud_descuento_pendiente:', bodyFact.solicitud_descuento_pendiente);
        console.log('=============================================\n');

        // Con mi fix: debe pedir aprobación porque acabamos de crear la solicitud
        const pidióAprobación = bodyFact.solicitud_descuento_pendiente === true
            || (typeof bodyFact.msj === 'string' && /solicitud|aprobaci[óo]n/i.test(bodyFact.msj));

        expect(pidióAprobación).toBe(true);
    });
});
