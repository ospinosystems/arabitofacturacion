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
}
