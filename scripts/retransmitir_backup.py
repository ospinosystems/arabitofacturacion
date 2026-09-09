# -*- coding: utf-8 -*-
"""
Retransmite a Arabito Central el histórico de una sucursal LEYENDO SU RESPALDO
(.sql de mysqldump, o .zip que lo contenga) — para tiendas ya migradas a
TitanioPOS cuya instalación vieja de arabitofacturacion ya no existe.

Es el equivalente de `php artisan retransmitir:conciliacion` pero sin Laravel ni
MySQL: parsea el dump directo y manda los mismos lotes al receptor
/retransmitirConciliacion de central (que upserta sin tocar cursores).

Ríos:
  - movimientos: tabla movimientos_inventariounitarios (kardex)
  - ventas:      items_pedidos + sus pedidos y pago_pedidos
                 (filtro: pedido con pago tipo<>4 o monto>0, o sin pagos —
                  incluye créditos foráneos, excluye transferencias tipo4/monto0)

Uso típico:
  python retransmitir_backup.py "C:\\...\\anacobackup.zip" --codigo anaco --api-key XXXX
  python retransmitir_backup.py dump.sql --codigo anaco --dry-run
  python retransmitir_backup.py dump.zip --codigo anaco --api-key X --solo movimientos --desde-id 152000

Autenticación: central exige codigo_origen + API key de ESA sucursal
(AuthenticateSucursal). Si la instalación vieja murió con su key, en central se
resetea con tinker:
  $s = \\App\\Models\\sucursal::where('codigo','anaco')->first();
  $s->api_key_hash = \\Illuminate\\Support\\Facades\\Hash::make('UNA-KEY-NUEVA');
  $s->require_api_key = true; $s->save();
(OJO: solo si la instalación vieja ya NO sincroniza — la key anterior deja de servir.)

El monitor en central: php artisan retransmision:monitor --seguir
"""

import argparse
import base64
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile
import zlib
from datetime import date, timedelta

TABLAS = ('movimientos_inventariounitarios', 'items_pedidos', 'pedidos', 'pago_pedidos')

# ── Parser del dump ─────────────────────────────────────────────────────────


def abrir_dump(ruta):
    """Devuelve un iterador de líneas de texto del .sql (directo o dentro de un .zip)."""
    if ruta.lower().endswith('.zip'):
        z = zipfile.ZipFile(ruta)
        sqls = [n for n in z.namelist() if n.lower().endswith('.sql')]
        if not sqls:
            sys.exit('El zip no contiene ningún .sql')
        import io
        return io.TextIOWrapper(z.open(sqls[0]), encoding='utf-8', errors='replace')
    return open(ruta, encoding='utf-8', errors='replace')


def parsear_columnas(linea_iter, tabla):
    """Tras un CREATE TABLE `tabla`, lee los nombres de columna en orden."""
    cols = []
    for linea in linea_iter:
        s = linea.strip()
        m = re.match(r'^`(\w+)`', s)
        if m:
            cols.append(m.group(1))
        elif s.startswith(')'):
            break
    return cols


def tuplas_de_values(seccion):
    """
    Generador de tuplas desde la parte `(...),(...)` de un INSERT extendido.
    Maneja comillas simples con \\ y '' , NULL y números. Es un autómata simple
    pero suficiente para dumps de mysqldump.
    """
    i = 0
    n = len(seccion)
    while i < n:
        while i < n and seccion[i] != '(':
            i += 1
        if i >= n:
            return
        i += 1
        fila = []
        campo = []
        en_cadena = False
        while i < n:
            c = seccion[i]
            if en_cadena:
                if c == '\\' and i + 1 < n:
                    nxt = seccion[i + 1]
                    campo.append({'n': '\n', 't': '\t', 'r': '\r', '0': '\0',
                                  "'": "'", '"': '"', '\\': '\\'}.get(nxt, nxt))
                    i += 2
                    continue
                if c == "'":
                    if i + 1 < n and seccion[i + 1] == "'":
                        campo.append("'")
                        i += 2
                        continue
                    en_cadena = False
                    i += 1
                    continue
                campo.append(c)
                i += 1
                continue
            if c == "'":
                en_cadena = True
                campo.append('\x00str\x00')   # marca: fue cadena (para no confundir NULL)
                i += 1
                continue
            if c == ',':
                fila.append(''.join(campo))
                campo = []
                i += 1
                continue
            if c == ')':
                fila.append(''.join(campo))
                yield fila
                i += 1
                break
            campo.append(c)
            i += 1


def valor(crudo):
    """Convierte el token crudo del dump a valor Python (None, str o número textual)."""
    if crudo.startswith('\x00str\x00'):
        return crudo.replace('\x00str\x00', '', 1)
    t = crudo.strip()
    if t.upper() == 'NULL':
        return None
    return t


def leer_tablas(ruta, nombres):
    """
    Recorre el dump UNA vez y devuelve {tabla: {'cols': [...], 'filas': [tuplas]}}
    solo para las tablas pedidas. Las filas quedan como listas de tokens crudos
    (se convierten al usarlas, para no pagar el costo en tablas gigantes).
    """
    resultado = {t: {'cols': None, 'filas': []} for t in nombres}
    f = abrir_dump(ruta)
    create_re = re.compile(r'^CREATE TABLE `(\w+)`')
    insert_re = re.compile(r'^INSERT INTO `(\w+)` VALUES (.*);\s*$', re.S)
    for linea in f:
        m = create_re.match(linea)
        if m and m.group(1) in resultado:
            resultado[m.group(1)]['cols'] = parsear_columnas(f, m.group(1))
            continue
        if linea.startswith('INSERT INTO `'):
            m = insert_re.match(linea)
            if not m or m.group(1) not in resultado:
                continue
            tabla = resultado[m.group(1)]
            for tupla in tuplas_de_values(m.group(2)):
                tabla['filas'].append(tupla)
    f.close()
    for t in nombres:
        if resultado[t]['cols'] is None:
            sys.exit(f'No encontré CREATE TABLE `{t}` en el dump — ¿es un respaldo completo de arabitofacturacion?')
    return resultado


def a_dicts(tabla, campos=None):
    """Convierte las tuplas crudas de leer_tablas a dicts (todas o solo `campos`)."""
    cols = tabla['cols']
    idx = {c: i for i, c in enumerate(cols)}
    quiero = campos or cols
    for fila in tabla['filas']:
        if len(fila) != len(cols):
            continue   # tupla corrupta: mejor saltarla que mandar basura
        yield {c: valor(fila[idx[c]]) for c in quiero}


# ── Transporte ──────────────────────────────────────────────────────────────


def gz64(obj):
    return base64.b64encode(zlib.compress(json.dumps(obj, ensure_ascii=False).encode('utf-8'))).decode('ascii')


def enviar_lote(args, params, etiqueta):
    """POST con 3 reintentos. Devuelve el JSON del receptor o None."""
    url = args.central.rstrip('/') + '/retransmitirConciliacion'
    params = dict(params)
    params['codigo_origen'] = args.codigo
    params['central_api_key'] = args.api_key
    data = urllib.parse.urlencode(params).encode('utf-8')
    esperas = [5, 20, 60]
    for intento in range(1, 4):
        try:
            req = urllib.request.Request(url, data=data, headers={
                'X-Sucursal-Api-Key': args.api_key,
                'Accept': 'application/json',
            })
            with urllib.request.urlopen(req, timeout=180) as r:
                cuerpo = r.read().decode('utf-8', 'replace')
            j = json.loads(cuerpo)
            if j.get('ok'):
                return j
            print(f'  {etiqueta}: central respondió {json.dumps(j)[:300]} (intento {intento}/3)')
        except urllib.error.HTTPError as e:
            print(f'  {etiqueta}: HTTP {e.code} {e.read().decode("utf-8", "replace")[:300]} (intento {intento}/3)')
        except Exception as e:  # noqa: BLE001 — red caída, timeout, JSON roto: todo se reintenta igual
            print(f'  {etiqueta}: {e} (intento {intento}/3)')
        if intento < 3:
            time.sleep(esperas[intento - 1])
    return None


# ── Ríos ────────────────────────────────────────────────────────────────────


def rio_movimientos(args, dump):
    campos = ['id', 'id_producto', 'id_pedido', 'id_usuario', 'cantidad', 'cantidadafter', 'origen', 'created_at']
    filas = [d for d in a_dicts(dump['movimientos_inventariounitarios'], campos)
             if d['created_at'] and args.desde <= d['created_at'][:10] <= args.hasta]
    filas.sort(key=lambda d: int(d['id']))
    if args.desde_id:
        ya = sum(1 for d in filas if int(d['id']) <= args.desde_id)
        filas = [d for d in filas if int(d['id']) > args.desde_id]
    else:
        ya = 0

    manifiesto = len(filas) + ya
    suma = sum(float(d['cantidad'] or 0) for d in filas)
    total_lotes = max(1, -(-manifiesto // args.lote))
    print(f'\n── MOVIMIENTOS: {manifiesto} filas (Σ cantidad del pendiente = {suma:g}) en {total_lotes} lotes ──')
    if not manifiesto:
        return True
    if args.dry_run:
        return True

    enviados = ya
    num_lote = ya // args.lote
    for i in range(0, len(filas), args.lote):
        lote = filas[i:i + args.lote]
        num_lote += 1
        enviados += len(lote)
        ultimo = lote[-1]['id']
        resp = enviar_lote(args, {
            'tipo': 'movimientos', 'lote': num_lote, 'total_lotes': total_lotes,
            'manifiesto_total': manifiesto, 'manifiesto_suma': f'{suma:.2f}',
            'desde': args.desde, 'hasta': args.hasta, 'enviados': len(lote),
            'payload': gz64(lote),
        }, f'movimientos lote {num_lote}/{total_lotes} (hasta id {ultimo})')
        if resp is None:
            print(f'ABORTADO. Retomar con: --solo movimientos --desde-id {ultimo}')
            return False
        print(f'  lote {num_lote}/{total_lotes} — {enviados}/{manifiesto} ({enviados * 100 // manifiesto}%) — central acumula {resp.get("recibidos")} de {resp.get("manifiesto")}')
    print(f'✔ movimientos: {enviados}/{manifiesto} transmitidos.')
    return True


def rio_ventas(args, dump):
    # pagos por pedido, para replicar el filtro del sync (+ créditos monto>0)
    pagos_por_pedido = {}
    for p in a_dicts(dump['pago_pedidos']):
        pagos_por_pedido.setdefault(p['id_pedido'], []).append(p)

    def pedido_valido(idp):
        pagos = pagos_por_pedido.get(idp)
        if not pagos:
            return True          # sin pagos: igual que el sync original
        return any(str(p.get('tipo')) != '4' or float(p.get('monto') or 0) > 0 for p in pagos)

    items = [d for d in a_dicts(dump['items_pedidos'])
             if d['created_at'] and args.desde <= d['created_at'][:10] <= args.hasta
             and d['id_producto'] is not None and pedido_valido(d['id_pedido'])]
    items.sort(key=lambda d: int(d['id']))
    if args.desde_id:
        items = [d for d in items if int(d['id']) > args.desde_id]

    pedidos_todos = {p['id']: p for p in a_dicts(dump['pedidos'])}

    manifiesto = len(items)
    total_lotes = max(1, -(-manifiesto // args.lote))
    print(f'\n── VENTAS: {manifiesto} líneas de venta en {total_lotes} lotes ──')
    if not manifiesto:
        return True
    if args.dry_run:
        return True

    enviados = 0
    for num_lote, i in enumerate(range(0, len(items), args.lote), start=1):
        lote = items[i:i + args.lote]
        enviados += len(lote)
        ids = {d['id_pedido'] for d in lote}
        payload = gz64({
            'items': lote,
            'pedidos': [pedidos_todos[x] for x in ids if x in pedidos_todos],
            'pagos': [p for x in ids for p in pagos_por_pedido.get(x, [])],
        })
        ultimo = lote[-1]['id']
        resp = enviar_lote(args, {
            'tipo': 'ventas', 'lote': num_lote, 'total_lotes': total_lotes,
            'manifiesto_total': manifiesto, 'desde': args.desde, 'hasta': args.hasta,
            'enviados': len(lote), 'payload': payload,
        }, f'ventas lote {num_lote}/{total_lotes} (hasta id {ultimo})')
        if resp is None:
            print(f'ABORTADO. Retomar con: --solo ventas --desde-id {ultimo}')
            return False
        print(f'  lote {num_lote}/{total_lotes} — {enviados}/{manifiesto} ({enviados * 100 // manifiesto}%) — central acumula {resp.get("recibidos")} de {resp.get("manifiesto")}')
    print(f'✔ ventas: {enviados}/{manifiesto} transmitidas.')
    return True


# ── Main ────────────────────────────────────────────────────────────────────


def main():
    # La consola de Windows suele venir en cp1252 y revienta con → o «»:
    # forzamos utf-8 (y si ni así puede, se degrada sin tumbar el proceso).
    # utf-8 (la consola de Windows viene en cp1252) y SIN buffer: cuando la
    # salida va a un archivo, Python la retiene hasta el final y el avance no
    # se ve; con line_buffering cada lote aparece al instante.
    for flujo in (sys.stdout, sys.stderr):
        try:
            flujo.reconfigure(encoding='utf-8', errors='replace', line_buffering=True)
        except Exception:  # noqa: BLE001
            pass

    ap = argparse.ArgumentParser(description='Retransmite un respaldo .sql de arabitofacturacion hacia central')
    ap.add_argument('dump', help='ruta al .sql o .zip del respaldo')
    ap.add_argument('--codigo', required=True, help='codigo de la sucursal en central (ej. anaco)')
    ap.add_argument('--api-key', default='', help='API key de la sucursal en central (obligatoria salvo --dry-run)')
    ap.add_argument('--api-key-file', default='', help='archivo con la API key (alternativa a --api-key, para no dejarla en el historial de la consola)')
    ap.add_argument('--central', default='https://titanio-central.com', help='URL base de central')
    ap.add_argument('--desde', default=(date.today() - timedelta(days=365)).isoformat())
    ap.add_argument('--hasta', default=date.today().isoformat())
    ap.add_argument('--lote', type=int, default=1000)
    ap.add_argument('--solo', choices=['movimientos', 'ventas'])
    ap.add_argument('--desde-id', type=int, default=0, dest='desde_id', help='retomar desde este id (con --solo)')
    ap.add_argument('--dry-run', action='store_true')
    args = ap.parse_args()

    if args.api_key_file:
        with open(args.api_key_file, encoding='utf-8') as f:
            args.api_key = f.read().strip()
    if not args.dry_run and not args.api_key:
        sys.exit('Falta --api-key o --api-key-file (la valida AuthenticateSucursal en central).')

    t0 = time.time()
    print(f'Leyendo dump: {args.dump}')
    necesarias = ['movimientos_inventariounitarios'] if args.solo == 'movimientos' \
        else list(TABLAS[1:]) if args.solo == 'ventas' else list(TABLAS)
    dump = leer_tablas(args.dump, necesarias)
    for t in necesarias:
        print(f'  {t}: {len(dump[t]["filas"])} filas en el dump')
    print(f'  parseo en {time.time() - t0:.1f}s | rango {args.desde} → {args.hasta} | lote {args.lote}'
          + (' | DRY-RUN' if args.dry_run else f' | destino {args.central} como «{args.codigo}»'))

    ok = True
    if args.solo in (None, 'movimientos'):
        ok = rio_movimientos(args, dump) and ok
    if args.solo in (None, 'ventas'):
        ok = rio_ventas(args, dump) and ok
    sys.exit(0 if ok else 1)


if __name__ == '__main__':
    main()
