<?php

namespace Tests\Unit;

use App\Services\Cuadre\CuadreCsvReader;
use PHPUnit\Framework\TestCase;

class CuadreCsvReaderTest extends TestCase
{
    private CuadreCsvReader $reader;
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new CuadreCsvReader();
        $this->tmp = sys_get_temp_dir() . '/cuadre_reader_test_' . uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function test_normaliza_fechas_en_varios_formatos(): void
    {
        $this->assertSame('2026-08-30', $this->reader->normalizarFecha('2026-08-30'));
        $this->assertSame('2026-08-30', $this->reader->normalizarFecha('30/08/2026'));
        $this->assertSame('2026-08-30', $this->reader->normalizarFecha('30-8-2026'));
        $this->assertSame('2026-08-30', $this->reader->normalizarFecha('2026/8/30'));
        $this->assertSame('2026-08-30', $this->reader->normalizarFecha('2026-08-30 14:05:00'));
        $this->assertSame('2026-08-30', $this->reader->normalizarFecha('46264')); // serial Excel
        $this->assertSame('', $this->reader->normalizarFecha('ayer'));
        $this->assertSame('', $this->reader->normalizarFecha(''));
    }

    public function test_normaliza_montos_con_separadores(): void
    {
        $this->assertSame('12345.67', $this->reader->normalizarMonto('12345.67'));
        $this->assertSame('12345.67', $this->reader->normalizarMonto('12.345,67'));
        $this->assertSame('12345.67', $this->reader->normalizarMonto('12,345.67'));
        $this->assertSame('12345.67', $this->reader->normalizarMonto('12345,67'));
        $this->assertSame('-95.00', $this->reader->normalizarMonto('-95,00'));
        $this->assertSame('1234567', $this->reader->normalizarMonto('1,234,567'));
        $this->assertNull($this->reader->normalizarMonto('abc'));
        $this->assertNull($this->reader->normalizarMonto(''));
    }

    public function test_lee_csv_agrupa_y_aplica_reduccion_del_dia(): void
    {
        $csv = $this->tmp . '/objetivo.csv';
        file_put_contents($csv, implode("\n", [
            'FECHA,CONCEPTO,CEDULA,SERIE,NOTA DE CREDITO,AFECTADA,NUMERO DE Z,FACTURA,VENTA,TIPO',
            '2024-01-15,ZZN0027439,,,,,,1-48,25152.30,FISCAL RANGO',
            '2024-01-15,ZZN0027439,,,,,,49,150.00,FISCAL UNITARIA',
            '2024-01-15,ZZN0028263,,,,,,1-44,28970.22,FISCAL RANGO',
            '2024-01-15,,,,,,,,-95.00,REDUCE EL TOTAL DE ESE DIA',
            '15/01/2024,ZZN0028263,,,,,,45,10.00,FISCAL UNITARIA',
            'basura,ZZN0028263,,,,,,46,10.00,FISCAL UNITARIA',
        ]));

        $normalizadas = $this->reader->leerNormalizado($csv);
        $this->assertCount(5, $normalizadas);
        $this->assertCount(1, $this->reader->razonesRechazo());

        $grupos = $this->reader->agregarPorDiaMaquina($normalizadas);
        $this->assertCount(2, $grupos);

        // Primera máquina del día recibe la reducción de -95.
        $this->assertSame('ZZN0027439', $grupos[0]['maquina_fiscal']);
        $this->assertSame('1', $grupos[0]['factura_inicio']);
        $this->assertSame('49', $grupos[0]['factura_fin']);
        $this->assertSame(49, $grupos[0]['cantidad']);
        $this->assertEquals(25152.30 + 150.00 - 95.00, (float) $grupos[0]['total_venta'], '', 0.0001);

        $this->assertSame('ZZN0028263', $grupos[1]['maquina_fiscal']);
        $this->assertSame(45, $grupos[1]['cantidad']);
        $this->assertEquals(28970.22 + 10.00, (float) $grupos[1]['total_venta'], '', 0.0001);
    }

    public function test_guarda_los_numeros_reales_de_facturas_no_consecutivas(): void
    {
        $csv = $this->tmp . '/serie_r.csv';
        file_put_contents($csv, implode("\n", [
            'FECHA,CONCEPTO,CEDULA,SERIE,NOTA DE CREDITO,AFECTADA,NUMERO DE Z,FACTURA,VENTA,TIPO',
            '2025-03-08,SERIE R,,,,,,2,537818.55,FISCAL UNITARIA',
            '2025-03-08,SERIE R,,,,,,8,456733.55,FISCAL UNITARIA',
            '2025-03-08,SERIE R,,,,,,7,92105.99,FISCAL UNITARIA',
            '2025-03-08,ZZN0029416,,,,,,10-12,300.00,FISCAL RANGO',
        ]));
        $grupos = $this->reader->agregarPorDiaMaquina($this->reader->leerNormalizado($csv));
        $this->assertCount(2, $grupos);

        $this->assertSame('SERIE R', $grupos[0]['maquina_fiscal']);
        $this->assertSame(3, $grupos[0]['cantidad']);
        $this->assertSame([2, 7, 8], $grupos[0]['numeros']);
        $this->assertSame('2', $grupos[0]['factura_inicio']);
        $this->assertSame('8', $grupos[0]['factura_fin']);

        $this->assertSame([10, 11, 12], $grupos[1]['numeros']);
    }

    public function test_agrupa_por_fila_del_libro_con_notas_de_credito_en_su_maquina(): void
    {
        $csv = $this->tmp . '/por_fila.csv';
        file_put_contents($csv, implode("\n", [
            'FECHA,CONCEPTO,CEDULA,SERIE,NOTA DE CREDITO,AFECTADA,NUMERO DE Z,FACTURA,VENTA,TIPO',
            '2026-03-30,Z7C7037700,,,,,,49946-50019,1585088.41,FISCAL RANGO',
            '2026-03-30,Z7C7037700,,,,,,49802-49945,2324391.58,FISCAL RANGO',
            '2026-03-30,SERIE R,,,,,,129,93019.31,FISCAL UNITARIA',
            '2026-03-30,SERIE R,,,,,,128,103160.85,FISCAL UNITARIA',
            '2026-03-30,Z7C7037700,,,,,,,-1000.00,REDUCE EL TOTAL DE ESE DIA',
            '2026-03-30,,,,,,,,-50.00,REDUCE EL TOTAL DE ESE DIA',
        ]));
        $grupos = $this->reader->agregarPorFila($this->reader->leerNormalizado($csv));
        $this->assertCount(4, $grupos);

        // Ordenados por máquina y primera factura; la reducción sin máquina va al primer grupo del día.
        $this->assertSame(['SERIE R', '128', 1, [128]], [$grupos[0]['maquina_fiscal'], $grupos[0]['factura_inicio'], $grupos[0]['cantidad'], $grupos[0]['numeros']]);
        $this->assertEquals(103160.85 - 50.00, (float) $grupos[0]['total_venta'], '', 0.0001);
        $this->assertSame('129', $grupos[1]['factura_inicio']);

        // Cada Z es su propio grupo; la nota de crédito de la máquina se resta a su primer Z.
        $this->assertSame(['Z7C7037700', '49802', '49945', 144], [$grupos[2]['maquina_fiscal'], $grupos[2]['factura_inicio'], $grupos[2]['factura_fin'], $grupos[2]['cantidad']]);
        $this->assertEquals(2324391.58 - 1000.00, (float) $grupos[2]['total_venta'], '', 0.0001);
        $this->assertSame(['49946', 74], [$grupos[3]['factura_inicio'], $grupos[3]['cantidad']]);
        $this->assertEquals(1585088.41, (float) $grupos[3]['total_venta'], '', 0.0001);
    }

    public function test_acepta_formato_antiguo_y_punto_y_coma(): void
    {
        $csv = $this->tmp . '/antiguo.csv';
        file_put_contents($csv, "FECHA;MAQUINA_FISCAL;N_Z;RANGO_FACTURA;TOTAL_VENTA\n2023-11-06;ZZN0027439;0002;2-48;25152,3032\n");
        $grupos = $this->reader->agregarPorDiaMaquina($this->reader->leerNormalizado($csv));
        $this->assertCount(1, $grupos);
        $this->assertSame(47, $grupos[0]['cantidad']);
        $this->assertSame('2', $grupos[0]['factura_inicio']);
        $this->assertEquals(25152.3032, (float) $grupos[0]['total_venta'], '', 0.0001);
    }

    public function test_fusiona_varios_archivos_en_csv_canonico_sin_duplicados(): void
    {
        $a = $this->tmp . '/junio.csv';
        $b = $this->tmp . '/julio.csv';
        file_put_contents($a, "FECHA,CONCEPTO,FACTURA,VENTA,TIPO\n2026-06-01,ZZN1,1-10,1000.00,FISCAL RANGO\n2026-06-01,ZZN1,1-10,1000.00,FISCAL RANGO\n");
        file_put_contents($b, "FECHA,CONCEPTO,FACTURA,VENTA,TIPO\n2026-07-01,ZZN1,11-20,2000.50,FISCAL RANGO\n2026-07-01,,,-10,REDUCE EL TOTAL DE ESE DIA\n");
        $destino = $this->tmp . '/fusion.csv';

        $stats = $this->reader->fusionarEnCsv([$b, $a], $destino);

        $this->assertSame(3, $stats['filas']);
        $this->assertSame(1, $stats['duplicadas_omitidas']);
        $this->assertSame('2026-06-01', $stats['fecha_min']);
        $this->assertSame('2026-07-01', $stats['fecha_max']);
        $this->assertSame(2, $stats['grupos']);
        $this->assertEquals(1000.0, (float) $stats['por_mes']['2026-06']['venta'], '', 0.0001);
        $this->assertEquals(1990.5, (float) $stats['por_mes']['2026-07']['venta'], '', 0.0001);

        $lineas = array_values(array_filter(explode("\n", file_get_contents($destino))));
        $this->assertSame(implode(',', CuadreCsvReader::CABECERA_CANONICA), str_replace('"', '', $lineas[0]));
        $this->assertStringStartsWith('2026-06-01,ZZN1,', $lineas[1]);

        // El CSV fusionado se vuelve a leer con los mismos grupos.
        $grupos = $this->reader->agregarPorDiaMaquina($this->reader->leerNormalizado($destino));
        $this->assertCount(2, $grupos);
        $this->assertEquals(1990.5, (float) $grupos[1]['total_venta'], '', 0.0001);
    }

    /**
     * Libro de ventas mensual (formato SENIAT de la contadora): título en las primeras filas, cabecera de dos
     * filas, "RESUMEN DE VENTAS <máquina>" por día, TOTAL VENTA como fórmula, notas de crédito, facturas
     * manuales SERIE R, filas ANULADA/solo retención, Z sin ventas, y salto de página (VAN…/…VIENEN).
     */
    public function test_lee_libro_de_ventas_xlsx_y_lo_convierte_al_formato_canonico(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet no instalado');
        }
        $xlsx = $this->tmp . '/LIBRO DE VENTAS MES DE ABRIL 26 SUC ANACO.xlsx';
        $this->crearLibroVentas($xlsx);

        $normalizadas = $this->reader->leerNormalizado($xlsx);
        $this->assertSame([], $this->reader->razonesRechazo());

        $resumen = array_map(fn ($n) => implode('|', [$n['fecha'], $n['maquina_fiscal'], $n['tipo'], $n['factura_inicio'] ?? '', $n['factura_fin'] ?? '', $n['cantidad'], round((float) $n['total_venta'], 2)]), $normalizadas);
        $this->assertSame([
            '2026-04-01|Z7C7037700|FISCAL RANGO|50259|50470|212|1260',
            '2026-04-01|Z7C7038463|FISCAL RANGO|55006|55010|5|580',
            '2026-04-01|SERIE R|FISCAL UNITARIA|131|131|1|1132.1',
            '2026-04-02|Z7C7037700|REDUCE EL TOTAL DE ESE DIA|||0|-232',
            '2026-04-02|Z7C7037700|FISCAL RANGO|50471|50480|10|2320',
            '2026-04-02|Z7C7037700|REDUCE EL TOTAL DE ESE DIA|||0|-116', // NC de cliente: máquina inferida por la factura afectada
            '2026-04-03|Z7C7037700|FISCAL RANGO|50481|50490|10|3480',   // tras el salto de página
        ], $resumen);

        $grupos = $this->reader->agregarPorDiaMaquina($normalizadas);
        $this->assertCount(5, $grupos);
        $g0402 = array_values(array_filter($grupos, fn ($g) => $g['fecha'] === '2026-04-02'))[0];
        $this->assertSame('Z7C7037700', $g0402['maquina_fiscal']);
        $this->assertSame(10, $g0402['cantidad']);
        $this->assertEqualsWithDelta(2320 - 232 - 116, (float) $g0402['total_venta'], 0.0001);

        // Fusionado: el CSV canónico se vuelve a leer igual y las estadísticas por tipo cuentan las SERIE R.
        $destino = $this->tmp . '/fusion_libro.csv';
        $stats = $this->reader->fusionarEnCsv([$xlsx], $destino);
        $this->assertSame(7, $stats['filas']);
        $this->assertSame(5, $stats['grupos']);
        $this->assertSame(1, $stats['por_tipo'][CuadreCsvReader::TIPO_FISCAL_UNITARIA]['filas']);
        $this->assertSame(['SERIE R'], $stats['por_tipo'][CuadreCsvReader::TIPO_FISCAL_UNITARIA]['conceptos']);
        $this->assertSame(238, $stats['por_mes']['2026-04']['facturas']);
        $this->assertEqualsWithDelta(1260 + 580 + 1132.102 - 232 + 2320 - 116 + 3480, (float) $stats['por_mes']['2026-04']['venta'], 0.001);
        $this->assertCount(5, $this->reader->agregarPorDiaMaquina($this->reader->leerNormalizado($destino)));
    }

    public function test_xlsx_plano_con_cabecera_que_no_esta_en_la_fila_1(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet no instalado');
        }
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $s = $ss->getActiveSheet();
        $s->setCellValue('A1', 'Objetivos de junio');
        $s->fromArray(['FECHA', 'CONCEPTO', 'FACTURA', 'VENTA', 'TIPO'], null, 'A3');
        $s->fromArray(['2026-06-01', 'ZZN1', '1-10', 1000, 'FISCAL RANGO'], null, 'A4');
        $s->fromArray(['2026-06-01', '', '', -10, 'REDUCE EL TOTAL DE ESE DIA'], null, 'A5');
        $xlsx = $this->tmp . '/plano.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($xlsx);

        $grupos = $this->reader->agregarPorDiaMaquina($this->reader->leerNormalizado($xlsx));
        $this->assertCount(1, $grupos);
        $this->assertSame(10, $grupos[0]['cantidad']);
        $this->assertEqualsWithDelta(990.0, (float) $grupos[0]['total_venta'], 0.0001);
    }

    /**
     * Errores de digitación reales de los libros: año equivocado en unas filas, extremos de rango mal escritos
     * (un dígito cambiado, extremos intercambiados entre las dos máquinas del mismo día) y una segunda sección
     * con el libro de otra sucursal pegado al final. Todo se corrige/omite con aviso, sin rechazar filas.
     */
    public function test_libro_corrige_anio_y_rangos_por_continuidad_y_omite_otra_sucursal(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet no instalado');
        }
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $s = $ss->getActiveSheet();
        $s->setCellValue('B5', 'CORRESPONDIENTE AL MES DE ENERO DE 2026');
        $s->setCellValue('B6', 'SUCURSAL ANACO, AV. FRANCISCO MIRANDA ESTADO ANZOATEGUI');
        $s->fromArray(['FECHA', 'CLIENTE', 'C.I./RIF.', 'N° DE FACTURA', 'Nº NOTA', 'Nº FC.', '', '', 'TOTAL'], null, 'B8');
        $s->fromArray(['', '', '', 'SERIE ', 'DE CREDITO', 'AFECTADA', 'Nº. Z', 'Nº. FACTURA', 'VENTA'], null, 'B9');
        $filas = [
            // fecha, máquina, Z, rango, total
            ['08/01/2025', 'Z7C7038463', '0235', '00038247-00038455', 1000], // año mal digitado (el libro es de enero 2026)
            ['08/01/2026', 'Z7C7037700', '0237', '00036501-00036717', 1000],
            ['09/01/2026', 'Z7C7038463', '0236', '00038456-00038643', 1000],
            ['09/01/2026', 'Z7C7037700', '0238', '00036718-00036913', 1000],
            ['10/01/2026', 'Z7C7038463', '0237', '00038644-00036828', 1000], // fin con un dígito cambiado (real 38828)
            ['10/01/2026', 'Z7C7037700', '0239', '00036914-00037082', 1000],
            ['11/01/2026', 'Z7C7038463', '0238', '00036829-00038888', 1000], // inicio copiado del error anterior
            ['11/01/2026', 'Z7C7037700', '0240', '00037083-00037158', 1000],
            ['12/01/2026', 'Z7C7038463', '0239', '00038889-00037337', 1000], // fines intercambiados entre las dos máquinas
            ['12/01/2026', 'Z7C7037700', '0241', '00037159-00039071', 1000], // (real: 39071 y 37337)
            ['13/01/2026', 'Z7C7038463', '0240', '00037338-00039220', 1000], // inicios copiados del error anterior
            ['13/01/2026', 'Z7C7037700', '0242', '00039072-00037492', 1000],
            ['14/01/2026', 'Z7C7038463', '0241', '00039221-00039369', 1000],
            ['14/01/2026', 'Z7C7037700', '0243', '00037493-00037636', 1000],
        ];
        $r = 10;
        foreach ($filas as [$fecha, $maq, $z, $rango, $total]) {
            $s->fromArray([$fecha, 'RESUMEN DE VENTAS ' . $maq, '', '', '', '', $z, $rango, $total], null, 'B' . $r);
            $r++;
        }
        // Segunda sección: otra sucursal pegada en el mismo libro.
        $r += 2;
        $s->setCellValue('B' . $r, 'SUCURSAL TUREN, ESTADO PORTUGUESA');
        $r += 2;
        $s->fromArray(['FECHA', 'CLIENTE', 'C.I./RIF.', 'N° DE FACTURA', 'Nº NOTA', 'Nº FC.', '', '', 'TOTAL'], null, 'B' . $r);
        $s->fromArray(['', '', '', 'SERIE ', 'DE CREDITO', 'AFECTADA', 'Nº. Z', 'Nº. FACTURA', 'VENTA'], null, 'B' . ($r + 1));
        $s->fromArray(['08/01/2026', 'RESUMEN DE VENTAS Z7C7037730', '', '', '', '', '0253', '00017570-00017590', 500], null, 'B' . ($r + 2));
        $s->fromArray(['09/01/2026', 'RESUMEN DE VENTAS Z7C7037730', '', '', '', '', '0254', '00017591-00017657', 500], null, 'B' . ($r + 3));
        $xlsx = $this->tmp . '/enero26.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($xlsx);

        $n = $this->reader->leerNormalizado($xlsx);
        $this->assertSame([], $this->reader->razonesRechazo());
        $this->assertCount(14, $n);

        $porClave = [];
        foreach ($n as $x) {
            $porClave[$x['fecha'] . '|' . $x['maquina_fiscal']] = $x['factura_inicio'] . '-' . $x['factura_fin'];
        }
        $this->assertSame('38247-38455', $porClave['2026-01-08|Z7C7038463']); // año corregido
        $this->assertArrayNotHasKey('2025-01-08|Z7C7038463', $porClave);
        $this->assertSame('38644-38828', $porClave['2026-01-10|Z7C7038463']); // dígito corregido (única edición de un dígito que encaja)
        $this->assertSame('38829-38888', $porClave['2026-01-11|Z7C7038463']); // inicio reencadenado
        $this->assertSame('38889-39071', $porClave['2026-01-12|Z7C7038463']); // fin tomado del extremo que la otra máquina tenía ese día
        $this->assertSame('39072-39220', $porClave['2026-01-13|Z7C7038463']); // inicio reencadenado
        $this->assertSame('37159-37337', $porClave['2026-01-12|Z7C7037700']);
        $this->assertSame('37338-37492', $porClave['2026-01-13|Z7C7037700']);
        $this->assertSame('37083-37158', $porClave['2026-01-11|Z7C7037700']); // fila correcta intacta
        $this->assertArrayNotHasKey('2026-01-08|Z7C7037730', $porClave);       // otra sucursal omitida

        $avisos = implode("\n", $this->reader->avisos());
        $this->assertStringContainsString('fecha 2025-01-08 corregida a 2026-01-08', $avisos);
        $this->assertStringContainsString('rango 38644-36828 corregido a 38644-38828', $avisos);
        $this->assertStringContainsString('rango 37159-39071 corregido a 37159-37337', $avisos);
        $this->assertStringContainsString('SUCURSAL TUREN, ESTADO PORTUGUESA" omitida (2 filas)', $avisos);
        $this->assertSame(1000.0 * 14, array_sum(array_map(fn ($x) => (float) $x['total_venta'], $n)));
    }

    private function crearLibroVentas(string $path): void
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $s = $ss->getActiveSheet();
        $s->setTitle('ABRIL 2026');
        $s->setCellValue('B2', 'OMAR EL HENAOUI SALAH (COMERCIALIZADORA "EL ARABITO 222" F.P)');
        $s->setCellValue('B4', 'LIBRO DE VENTAS');
        $s->setCellValue('B5', 'CORRESPONDIENTE AL MES DE ABRIL DE 2026');

        $cabecera = function (int $r) use ($s): void {
            $s->fromArray(['FECHA', 'CLIENTE', 'C.I./RIF.', 'N° DE FACTURA', 'Nº NOTA', 'Nº FC.', '', '', 'TOTAL', 'VENTAS A NO CONTRIBUYENTES', '', '', '', 'VENTAS A CONTRIBUYENTES', '', '', '', 'AGENTE', 'MONTO DE', '% DE ', 'Nº', 'FECHA DE'], null, 'B' . $r);
            $s->fromArray(['', '', '', 'SERIE ', 'DE CREDITO', 'AFECTADA', 'Nº. Z', 'Nº. FACTURA', 'VENTA', 'EXENTAS', 'BASE', '%', 'I.V.A.', 'EXENTAS', 'BASE', '%', 'I.V.A.', 'RETENCION', 'IVA RET', 'IVA RET', 'COMPROBANTE', 'COMPROBANTE'], null, 'B' . ($r + 1));
        };
        $fila = function (int $r, string $fecha, string $cliente, array $celdas) use ($s): void {
            $s->setCellValue('B' . $r, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($fecha)));
            $s->getStyle('B' . $r)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $s->setCellValue('C' . $r, $cliente);
            $s->setCellValue('J' . $r, "=K{$r}+L{$r}+N{$r}+O{$r}+P{$r}+R{$r}");
            $s->setCellValue('M' . $r, 16);
            $s->setCellValue('N' . $r, "=L{$r}*16%");
            $s->setCellValue('Q' . $r, 16);
            $s->setCellValue('R' . $r, "=P{$r}*16%");
            foreach ($celdas as $col => $v) {
                $s->setCellValue($col . $r, $v);
            }
        };

        $cabecera(8);
        $fila(10, '2026-04-01', 'RESUMEN DE VENTAS Z7C7037700', ['H' => '0323', 'I' => '00050259-00050470', 'K' => 100, 'L' => 1000]);
        $fila(11, '2026-04-01', 'RESUMEN DE VENTAS Z7C7038463', ['H' => '0318', 'I' => '00055006-00055010', 'L' => 500]);
        $fila(12, '2026-04-01', 'SERVICIOS Y TRANSPORTE DEL ESTE, C.A.', ['D' => 'J-29878584-5', 'E' => 'SERIE R 00000131', 'P' => 975.95, 'S' => 'X']);
        $fila(13, '2026-04-01', 'ANULADA', ['E' => 'SERIE R 00000132']);
        $fila(14, '2026-04-02', 'RESUMEN DE VENTAS Z7C7037700', ['F' => '00000003', 'G' => '50300', 'L' => -200]);
        $fila(15, '2026-04-02', 'RESUMEN DE VENTAS Z7C7037700', ['H' => '0324', 'I' => '00050471-00050480', 'L' => 2000]);
        $fila(16, '2026-04-02', 'RESUMEN DE VENTAS Z7C7038463', ['H' => '0319', 'I' => '0', 'K' => 0, 'L' => 0]);
        $fila(17, '2026-04-02', 'DISTRIBUIDORA PANDOR, C.A.', ['D' => 'J-30998694-5', 'F' => 'SERIE R 00000001', 'G' => '00050260', 'P' => -100, 'S' => 'X']);
        $fila(18, '2026-04-03', 'MULTI-TIENDA LOS 7 HERMANOS, C.A.', ['D' => 'J-29744071-2', 'S' => 'X', 'T' => 693.33, 'U' => 0.75, 'V' => '20260400001476']);
        $s->setCellValue('C19', 'VAN…');
        $s->setCellValue('J19', '=SUM(J10:J18)');
        $cabecera(21);
        $s->setCellValue('C23', '…VIENEN');
        $s->setCellValue('J23', '=J19');
        $fila(24, '2026-04-03', 'RESUMEN DE VENTAS Z7C7037700', ['H' => '0325', 'I' => '00050481-00050490', 'L' => 3000]);
        $s->setCellValue('C25', 'TOTALES');
        $s->setCellValue('J25', '=J23+J24');

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();
    }
}
