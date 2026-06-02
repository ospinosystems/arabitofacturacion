// @ts-check
/**
 * Mock Pinpad — simula el dispositivo POS físico en :9001.
 *
 * `enviarTransaccionPOS` (sendCentral.php) le pega a `http://{ip_pinpad}/transaction`.
 * Los tests inyectan `ip_pinpad=127.0.0.1:9001` en cada llamada vía Playwright
 * `page.route` interceptor, así no tocamos la columna ip_pinpad del cajero real.
 *
 * Modos controlables por header X-Mock-Mode (que el test inyecta en la request):
 *   approve (default)  → APROBADO con referencia y aprobación estables
 *   deny               → RECHAZADO responsecode=51
 *   timeout            → cuelga 30s (testear el catch del frontend)
 *   indeterminate      → 500 (forzar consulta a central via queryTransaccionPosCentral)
 *
 * El `amount` se devuelve en centavos (formato del POS real: 1000 = Bs 10.00).
 */
const express = require('express');

let server = null;
let lastPort = null;

async function startPinpadMock(port = 9001) {
    if (server) {
        console.log(`[mock-pinpad] ya estaba corriendo en :${lastPort}`);
        return;
    }
    const app = express();
    app.use(express.json({ limit: '1mb' }));

    app.post('/transaction', async (req, res) => {
        const mode = req.headers['x-mock-mode'] || 'approve';
        const { monto, numeroOrden } = req.body || {};

        if (mode === 'timeout') {
            await new Promise(r => setTimeout(r, 30_000));
        }
        if (mode === 'indeterminate') {
            return res.status(500).json({ success: false, message: 'INDETERMINATE' });
        }

        // Generar referencia pseudo-aleatoria pero estable por (numeroOrden, monto)
        const seed = String(numeroOrden) + '|' + String(monto);
        let h = 0;
        for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) | 0;
        const ref = String(Math.abs(h) % 1000000).padStart(6, '0');
        const approval = String((Math.abs(h) >> 4) % 1000000).padStart(6, '0');

        if (mode === 'deny') {
            return res.json({
                success: false,
                message: 'RECHAZADO',
                responsecode: '51',
                reference: '',
                amount: monto,
                ordernumber: numeroOrden,
            });
        }

        // default approve
        res.json({
            success: true,
            message: 'APROBADO',
            reference: ref,
            ordernumber: numeroOrden,
            sequence: Number(ref + approval),
            approval,
            lote: 1,
            responsecode: '00',
            datetime: new Date().toLocaleString('es-VE'),
            amount: monto,
            commerce: 'MOCK_PINPAD',
            cardtype: 3,
            authid: approval,
            cardNumber: '541105....' + ref.slice(-4),
            idmerchant: '0000000000',
            terminal: 'MOCK0001',
            bank: '0134',
            bankmessage: ref,
            tvr: '0000000000',
            arqc: 'AABBCCDD'.padEnd(16, '0'),
            tsi: 'E800',
            na: 'NA00',
            aid: 'A0000000041010',
        });
    });

    app.get('/health', (_req, res) => res.json({ ok: true, service: 'pinpad-mock', port }));

    return new Promise((resolve, reject) => {
        server = app.listen(port, '127.0.0.1', (err) => {
            if (err) return reject(err);
            lastPort = port;
            console.log(`[mock-pinpad] listening on 127.0.0.1:${port}`);
            resolve();
        });
        server.on('error', reject);
    });
}

async function stopPinpadMock() {
    if (!server) return;
    await new Promise((resolve) => server.close(resolve));
    server = null;
    console.log('[mock-pinpad] stopped');
}

module.exports = { startPinpadMock, stopPinpadMock };
