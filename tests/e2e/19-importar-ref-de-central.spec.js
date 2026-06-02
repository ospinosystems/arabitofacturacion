// @ts-check
const { test, expect } = require('@playwright/test');
const { setupCajero, login, abrirCaja, config } = require('./_support/helpers');
const { assertRefExiste, assertRefNoExiste } = require('./_support/strict-assertions');
const db = require('./_support/db-helpers');

const baseUrl = () => config.arabitofacturacion.baseUrl;

test.describe('Importar ref de central (caso ref en central pero no local)', () => {
    test('API: ref existe en central → POST /importarRefDeCentral la persiste local', async ({ page }) => {
        const watcher = await setupCajero(page);
        await login(page);
        await abrirCaja(page);

        // 1) Crear pedido + item + cliente
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
        if (cli?.id) {
            await page.request.post(baseUrl() + '/setpersonacarrito', {
                data: { numero_factura: idPedido, id_cliente: cli.id },
            });
        }

        // 2) Estado inicial: NO hay ref local con esta descripción
        const descripcion = 'IMPORT-TEST-' + Date.now();
        const banco = '0134';
        const monto = 5.00;

        await assertRefNoExiste(page, { idPedido, descripcion, banco });

        // 3) Insertar ref en central (sin local) usando endpoint fixture de testing
        const respFixture = await page.request.post(baseUrl() + '/testing/fixture-ref-central', {
            data: { banco, descripcion, monto, id_pedido: idPedido },
        });
        const fixtureJson = await respFixture.json();
        expect(fixtureJson.estado, 'fixture-ref-central debería crear el row en central').toBe(true);

        // 4) Reintentar alta normal — debe rebotar con duplicado
        const respAdd = await page.request.post(baseUrl() + '/addRefPago', {
            data: {
                tipo: 1,
                descripcion,
                monto,
                banco,
                id_pedido: idPedido,
                categoria: 'central',
            },
        });
        const bodyAdd = await respAdd.json();
        // Puede que se cree local sin rebotar (porque la validación de duplicado en addRefPago local
        // mira pagos_referencias, no central). Documentamos el comportamiento real.

        // 5) Si NO se creó local (rebote), usar import — éste es el camino que arreglamos
        const refsTrasAdd = await db.getRefs(page, { id_pedido: idPedido, descripcion, banco });
        if (refsTrasAdd.count === 0) {
            // No hay nada local — el cajero debe importar
            const respImp = await page.request.post(baseUrl() + '/importarRefDeCentral', {
                data: { id_pedido: idPedido, banco, descripcion, monto },
            });
            const bodyImp = await respImp.json();

            // HARD: import debe ser exitoso
            expect(bodyImp.estado, `importarRefDeCentral falló: ${bodyImp.msj}`).toBe(true);
            expect(bodyImp.id_referencia, 'import debe devolver id_referencia').toBeGreaterThan(0);
            expect(bodyImp.fuente).toBe('importada_de_central');
        }

        // 6) HARD: ahora SI existe la ref local con los datos correctos
        const refsFinales = await assertRefExiste(page, { idPedido, descripcion, banco });
        expect(parseFloat(refsFinales[0].monto)).toBeCloseTo(monto, 2);

        // 7) HARD: idempotencia — segundo import debe devolver "ya existía local"
        const respImp2 = await page.request.post(baseUrl() + '/importarRefDeCentral', {
            data: { id_pedido: idPedido, banco, descripcion, monto },
        });
        const bodyImp2 = await respImp2.json();
        expect(bodyImp2.estado, 'segundo import debe seguir devolviendo estado true').toBe(true);
        expect(bodyImp2.fuente).toBe('local_ya_existia');

        // 8) Limpieza
        await db.cleanupRefs(page, idPedido);

        await watcher.assertClean();
    });
});
