# E2E Tests — arabitofacturacion (cross-project con arabitocentral)

Suite Playwright que cubre los flujos críticos de caja end-to-end, incluyendo
las aprobaciones que requieren cruzar entre los dos proyectos.

## Setup (5 minutos, una sola vez)

### 1. Copiar y llenar el config
```powershell
copy tests\e2e\.config.example.json tests\e2e\.config.json
# Editá tests/e2e/.config.json con tus credenciales reales:
#   - cajero de prueba (debe existir en arabitofacturacion)
#   - admin de prueba (debe existir en arabitocentral)
#   - código de barras de un producto que ya tengás en tu DB local
#   - identificación del cliente de prueba que ya tengás
#   - tasas vigentes (bs_por_usd, cop_por_usd)
```

### 2. Verificar prerequisitos
- `arabitofacturacion` corriendo en el `baseUrl` del config (por defecto `:8000`)
- `arabitocentral` corriendo en el `baseUrl` del config (por defecto `:8001`)
- Tu DB local tiene productos/clientes/bancos válidos
- Chromium instalado: `npm run test:e2e:install`

### 3. Correr
```powershell
npm run test:e2e                 # todo
npm run test:e2e:headed          # ver browser
npm run test:e2e:debug           # paso a paso
npm run test:e2e:report          # abrir HTML report
npm run test:e2e -- tests/e2e/02-venta-efectivo.spec.js   # uno solo
```

## Arquitectura

```
tests/e2e/
├── .config.example.json         # plantilla para .config.json (commiteable)
├── .config.json                 # TUS credenciales (NO commitear)
├── _support/
│   ├── config.js                # carga y valida .config.json
│   ├── global-setup.js          # levanta mock pinpad
│   ├── global-teardown.js       # apaga mock
│   ├── helpers.js               # setupCajero, login, nuevoPedido, etc.
│   ├── central-helpers.js       # abrirCentral, aprobarUltimaSolicitud*, etc.
│   └── mocks/
│       └── pinpad-mock.js       # :9001 — único componente que mockeamos
├── 01-smoke-login.spec.js
├── 02-venta-efectivo.spec.js
├── 03-items-crud.spec.js
├── 04-debito-pinpad.spec.js
├── 05-transferencia-central-aprobacion.spec.js     # CROSS-PROJECT
├── 06-autovalidar-transferencia.spec.js
├── 07-descuento-aprobacion.spec.js                 # CROSS-PROJECT
├── 08-credito-aprobacion.spec.js                   # CROSS-PROJECT
├── 09-multipago-coherencia-tasa.spec.js
├── 10-sobrante-debito.spec.js
└── (resto a agregar: devoluciones, multipedidos, etc.)
```

## Único mock: Pinpad en :9001

`enviarTransaccionPOS` (sendCentral.php) le pega a `http://{ip_pinpad}/transaction`.
Para que no toque hardware real, los tests **interceptan la llamada con
`page.route` e inyectan `ip_pinpad=127.0.0.1:9001`** en el body — así el backend
le pega al mock sin que tengamos que modificar la columna `ip_pinpad` del cajero
en tu DB.

Modos controlables (header `X-Mock-Mode` que el test inyecta):
- `approve` (default) → APROBADO
- `deny` → RECHAZADO responsecode=51
- `timeout` → cuelga 30s
- `indeterminate` → 500 (testea el camino "consulta a central")

## Tests cross-project

Los marcados ★ usan dos contextos de browser (cajero + admin central) y
verifican el ciclo completo de aprobación:

- ★ **05 Transferencia central**: cajero carga ref → admin aprueba → cajero factura
- ★ **07 Descuento**: cajero pide descuento + método pago → admin aprueba → cajero factura
- ★ **08 Crédito**: cajero pide crédito → admin aprueba → factura

## Aserciones de coherencia (lo que el usuario pidió)

Cada test que toca dinero verifica:
- El total mostrado en USD se preserva al backend (`body.estado === true`)
- En pagos con débito: `pos_amount` (centavos) coincide con monto solicitado
- En descuento backend: NO se crea solicitud `monto_porcentaje` (cambio 2026-05-27 — solo una solicitud `metodo_pago` al facturar)
- Multipago: la suma USD de todos los métodos debe coincidir con `clean_total` (tolerancia 0.1 USD)
- Botones no quedan disabled forever (`toBeEnabled` post-acción)

## Iterar cuando algo rompe

1. Correrlo con `--headed --debug`.
2. Si rompe por selector → te lo refino o agrego `data-test=` en pagarMain.
3. Si rompe por timing → agregar `waitForResponse` después de la acción.
4. Si rompe por datos → ajustar `.config.json`.

**Política**: cada bug en producción → primero el test que lo reproduce, después el fix.
