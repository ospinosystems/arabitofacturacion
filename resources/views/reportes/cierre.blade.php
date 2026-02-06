<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reporte de Cierre</title>
	<style type="text/css">
		.bg-white{
			background-color: white;
		}
		body{

		}
		.long-text{
			width: 400px;
		}

		table, td, th {  
		  border: 1px solid #ddd;
		  text-align: center;
		}
		th{
			font-size:15px;
		}

		table {
		  border-collapse: collapse;
		  width: 100%;
		}
		.border-top{
			border-top: 5px solid #000000;

		}

		th, td {
		  padding: 5px;
		}
		.right{
			text-align: right !important;
		}

		.left{
			text-align: left !important;
		}
		
		.margin-bottom{
			margin-bottom:5px;
		}
		.amarillo{
			background-color:#FFFF84;
		}
		.verde{
			background-color:#84FF8D;
		}
		.rojo{
			background-color:#FF8484;
		}
		.sin-borde{
			border:none;
		}
		.text-warning{
			background: yellow;
		}
		.text-success{
			background: green;
			color: white;
		}
		.text-danger{
			background: rgb(230, 0, 0);
			color: white;
		}
		.text-success-only{
			color: green;
		}
		.fs-3{
			font-size: xx-large;
			font-weight: bold;
		}

		.table-dark{
			background-color: #f2f2f2;
		}
		.container{
			width: 100%;
		}
		h1{
			font-size:3em;
		}
		.d-flex div{
			display: inline-block;
		}
		
		.tr-striped:nth-child(odd) {
            background-color: #8F9AA5;
        }
		.bg-danger-light{
			background-color: #fec7d1 !important; 
		}
		.bg-success-light{
			background-color: #c4f7c7 !important; 
		}

		

	</style>


</head>
<body>
	<div class="container bg-white">
		<table class="table">
			<tbody>
				<tr>
					<td colspan="6">
						<h2>{{$sucursal->sucursal}}</h2>
						@if(isset($url_descargar_csv) && $url_descargar_csv)
						<p style="margin-top: 10px;">
							<a href="{{ $url_descargar_csv }}" class="text-primary" style="font-weight: bold;" download>📥 Descargar CSV conciliación (una fila por pedido: pagos + ítems + totales)</a>
						</p>
						@endif
					</td>
				</tr>
				<tr>
					<td>
						<img src="{{asset('images/logo-titanio.png')}}" width="100px" >
						
<!-- 
						<h5>
							{{$sucursal->nombre_registro}}
							<br>
							<b>RIF. {{$sucursal->rif}}</b>
						</h5>
						
						<hr>
						<span>
							Domicilio Fiscal: {{$sucursal->direccion_registro}}
						</span> -->

					</td>
				
					<td style="font-size: 18px;">
						<span style="font-weight: bold;">TASA</span><br>
						<span>{{($cierre->tasa)}}</span>
					</td>
					<td style="font-size: 18px;">
						<span style="font-weight: bold;">{{$cierre->fecha}}</span><br>
						@if($totalizarcierre ?? false)
							<span style="font-size: 16px; font-weight: 600;">ADMINISTRADOR - TOTAL DE {{$numero_cajas ?? 0}} CAJA(S)</span>
						@else
							<span style="font-size: 16px; font-weight: 600;">CAJERO: {{$cierre->usuario->usuario}}</span>
						@endif
					</td>

					<th class="center" colspan="2" style="font-size: 18px;">
						<span style="font-weight:bold;">VENTAS DEL DÍA</span> <br>
						<span class="text-success-only" style="font-size: 24px; font-weight: bold;">{{($facturado["numventas"])}}</span>
					</th>
					
				</tr>
				
				{{-- CUADRE CONSOLIDADO CON MONEDAS ORIGINALES --}}
				@if(isset($cuadre_consolidado) && $cuadre_consolidado)
				<tr>
					<td colspan="6">
						<h2 class="text-center">CUADRE CONSOLIDADO - TOTAL DE {{$numero_cajas ?? 1}} CAJA(S)</h2>
						<table class="table" style="margin-top: 20px;">
							<thead>
								<tr class="table-dark">
									<th>Tipo</th>
									<th class="text-right">Saldo Inicial</th>
									<th class="text-right">Real</th>
									<th class="text-right">Digital</th>
									<th class="text-right">Diferencia</th>
									<th class="text-center">Estado</th>
								</tr>
							</thead>
							<tbody>
								{{-- EFECTIVO --}}
								<tr>
									<td colspan="6" style="background-color: #e3f2fd; font-weight: bold;">EFECTIVO</td>
								</tr>
								{{-- Efectivo USD --}}
								@if(isset($cuadre_consolidado['efectivo_usd']))
								<tr>
									<td class="left"><strong>Dólares (USD)</strong></td>
									<td class="text-right">{{number_format(floatval($cuadre_consolidado['efectivo_usd']['inicial'] ?? 0), 2)}}</td>
									<td class="text-right"><strong>${{number_format(floatval($cuadre_consolidado['efectivo_usd']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">${{number_format(floatval($cuadre_consolidado['efectivo_usd']['digital'] ?? 0), 2)}}</td>
									<td class="text-right {{($cuadre_consolidado['efectivo_usd']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_consolidado['efectivo_usd']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
										<strong>${{number_format(floatval($cuadre_consolidado['efectivo_usd']['diferencia'] ?? 0), 2)}}</strong>
					</td>
									<td class="text-center" style="@if(($cuadre_consolidado['efectivo_usd']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_consolidado['efectivo_usd']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(($cuadre_consolidado['efectivo_usd']['estado'] ?? '') == 'cuadrado')
											<span class="badge bg-success">CUADRADO</span>
										@elseif(($cuadre_consolidado['efectivo_usd']['diferencia'] ?? 0) > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
					</td>
								</tr>
								@endif
								{{-- Efectivo BS --}}
								@if(isset($cuadre_consolidado['efectivo_bs']))
								<tr>
									<td class="left"><strong>Bolívares (Bs)</strong></td>
									<td class="text-right">{{number_format(floatval($cuadre_consolidado['efectivo_bs']['inicial'] ?? 0), 2)}}</td>
									<td class="text-right"><strong>Bs {{number_format(floatval($cuadre_consolidado['efectivo_bs']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">Bs {{number_format(floatval($cuadre_consolidado['efectivo_bs']['digital'] ?? 0), 2)}}</td>
									<td class="text-right {{($cuadre_consolidado['efectivo_bs']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_consolidado['efectivo_bs']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
										<strong>Bs {{number_format(floatval($cuadre_consolidado['efectivo_bs']['diferencia'] ?? 0), 2)}}</strong>
									</td>
									<td class="text-center" style="@if(($cuadre_consolidado['efectivo_bs']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_consolidado['efectivo_bs']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(($cuadre_consolidado['efectivo_bs']['estado'] ?? '') == 'cuadrado')
											<span class="badge bg-success">CUADRADO</span>
										@elseif(($cuadre_consolidado['efectivo_bs']['diferencia'] ?? 0) > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
					</td>
				</tr>
								@endif
								{{-- Efectivo COP --}}
								@if(isset($cuadre_consolidado['efectivo_cop']))
								<tr>
									<td class="left"><strong>Pesos (COP)</strong></td>
									<td class="text-right">{{number_format(floatval($cuadre_consolidado['efectivo_cop']['inicial'] ?? 0), 2)}}</td>
									<td class="text-right"><strong>$ {{number_format(floatval($cuadre_consolidado['efectivo_cop']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">$ {{number_format(floatval($cuadre_consolidado['efectivo_cop']['digital'] ?? 0), 2)}}</td>
									<td class="text-right {{($cuadre_consolidado['efectivo_cop']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_consolidado['efectivo_cop']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
										<strong>$ {{number_format(floatval($cuadre_consolidado['efectivo_cop']['diferencia'] ?? 0), 2)}}</strong>
									</td>
									<td class="text-center" style="@if(($cuadre_consolidado['efectivo_cop']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_consolidado['efectivo_cop']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(($cuadre_consolidado['efectivo_cop']['estado'] ?? '') == 'cuadrado')
											<span class="badge bg-success">CUADRADO</span>
										@elseif(($cuadre_consolidado['efectivo_cop']['diferencia'] ?? 0) > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
									</td>
								</tr>
								@endif
								
								{{-- DÉBITO --}}
								<tr>
									<td colspan="6" style="background-color: #fff3cd; font-weight: bold;">DÉBITO</td>
								</tr>
								{{-- Débito Pinpad --}}
								@if(isset($cuadre_consolidado['debito_pinpad']))
								<tr>
									<td class="left"><strong>Pinpad</strong></td>
									<td class="text-right">-</td>
									<td class="text-right"><strong>Bs {{number_format(floatval($cuadre_consolidado['debito_pinpad']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">Bs {{number_format(floatval($cuadre_consolidado['debito_pinpad']['digital'] ?? 0), 2)}}</td>
									<td class="text-right {{($cuadre_consolidado['debito_pinpad']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_consolidado['debito_pinpad']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
										<strong>Bs {{number_format(floatval($cuadre_consolidado['debito_pinpad']['diferencia'] ?? 0), 2)}}</strong>
									</td>
									<td class="text-center" style="@if(($cuadre_consolidado['debito_pinpad']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_consolidado['debito_pinpad']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(($cuadre_consolidado['debito_pinpad']['estado'] ?? '') == 'cuadrado')
											<span class="badge bg-success">CUADRADO</span>
										@elseif(($cuadre_consolidado['debito_pinpad']['diferencia'] ?? 0) > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
									</td>
								</tr>
								@endif
								{{-- Débito Otros Puntos --}}
								@if(isset($cuadre_consolidado['debito_otros']))
								<tr>
									<td class="left"><strong>Otros Puntos</strong></td>
									<td class="text-right">-</td>
									<td class="text-right"><strong>Bs {{number_format(floatval($cuadre_consolidado['debito_otros']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">Bs {{number_format(floatval($cuadre_consolidado['debito_otros']['digital'] ?? 0), 2)}}</td>
									<td class="text-right {{($cuadre_consolidado['debito_otros']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_consolidado['debito_otros']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
										<strong>Bs {{number_format(floatval($cuadre_consolidado['debito_otros']['diferencia'] ?? 0), 2)}}</strong>
									</td>
									<td class="text-center" style="@if(($cuadre_consolidado['debito_otros']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_consolidado['debito_otros']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(($cuadre_consolidado['debito_otros']['estado'] ?? '') == 'cuadrado')
											<span class="badge bg-success">CUADRADO</span>
										@elseif(($cuadre_consolidado['debito_otros']['diferencia'] ?? 0) > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
									</td>
								</tr>
								@endif
								
								{{-- TRANSFERENCIA --}}
								@if(isset($cuadre_consolidado['transferencia']))
								<tr>
									<td colspan="6" style="background-color: #d1ecf1; font-weight: bold;">TRANSFERENCIA</td>
								</tr>
								<tr>
									<td class="left"><strong>Transferencia</strong></td>
									<td class="text-right">-</td>
									<td class="text-right"><strong>${{number_format(floatval($cuadre_consolidado['transferencia']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">${{number_format(floatval($cuadre_consolidado['transferencia']['digital'] ?? 0), 2)}}</td>
									<td class="text-right text-success">
										<strong>${{number_format(floatval($cuadre_consolidado['transferencia']['diferencia'] ?? 0), 2)}}</strong>
									</td>
									<td class="text-center" style="background-color: #d4edda; font-weight: bold;">
										<span class="badge bg-success">CUADRADO</span>
									</td>
								</tr>
								@endif
							</tbody>
						</table>
						
						{{-- DATOS INTRODUCIDOS TOTAL --}}
						<div style="margin-top: 20px; margin-bottom: 20px;">
							<h5 style="color: #1e3a8a; font-weight: bold; margin-bottom: 15px; text-align: center;">
								<i class="fa fa-calculator"></i> Datos Introducidos Total
							</h5>
							<div style="display: flex; gap: 15px; justify-content: space-between;">
								{{-- Tabla 1: Efectivo Real Contado --}}
								<table class="table" style="width: 33.33%; margin: 0; border: 2px solid #90caf9;">
									<thead style="background-color: #e3f2fd;">
										<tr>
											<th colspan="2" style="text-align: center; color: #1565c0; font-weight: bold; border-bottom: 2px solid #90caf9;">
												<i class="fa fa-money-bill-wave"></i> Efectivo Real Contado
					</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td style="font-weight: bold;">USD:</td>
											<td class="text-right"><strong>${{number_format(floatval($cierre->efectivo_actual ?? 0), 2)}}</strong></td>
										</tr>
										<tr>
											<td style="font-weight: bold;">Bs:</td>
											<td class="text-right"><strong>Bs {{number_format(floatval($cierre->efectivo_actual_bs ?? 0), 2)}}</strong></td>
										</tr>
										<tr>
											<td style="font-weight: bold;">COP:</td>
											<td class="text-right"><strong>$ {{number_format(floatval($cierre->efectivo_actual_cop ?? 0), 2)}}</strong></td>
										</tr>
									</tbody>
								</table>
								
								{{-- Tabla 2: Dejar en Caja --}}
								<table class="table" style="width: 33.33%; margin: 0; border: 2px solid #ffc107;">
									<thead style="background-color: #fff3cd;">
										<tr>
											<th colspan="2" style="text-align: center; color: #856404; font-weight: bold; border-bottom: 2px solid #ffc107;">
												<i class="fa fa-archive"></i> Dejar en Caja
											</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td style="font-weight: bold;">USD:</td>
											<td class="text-right"><strong>${{number_format(floatval($cierre->dejar_dolar ?? 0), 2)}}</strong></td>
										</tr>
										<tr>
											<td style="font-weight: bold;">Bs:</td>
											<td class="text-right"><strong>Bs {{number_format(floatval($cierre->dejar_bss ?? 0), 2)}}</strong></td>
										</tr>
										<tr>
											<td style="font-weight: bold;">COP:</td>
											<td class="text-right"><strong>$ {{number_format(floatval($cierre->dejar_peso ?? 0), 2)}}</strong></td>
										</tr>
									</tbody>
								</table>
								
								{{-- Tabla 3: Efectivo Guardado --}}
								<table class="table" style="width: 33.33%; margin: 0; border: 2px solid #28a745;">
									<thead style="background-color: #d4edda;">
										<tr>
											<th colspan="2" style="text-align: center; color: #155724; font-weight: bold; border-bottom: 2px solid #28a745;">
												<i class="fa fa-piggy-bank"></i> Efectivo Guardado
					</th>
				</tr>
									</thead>
									<tbody>
										<tr>
											<td style="font-weight: bold;">USD:</td>
											<td class="text-right"><strong>${{moneda($cierre->efectivo_guardado)}}</strong></td>
										</tr>
										<tr>
											<td style="font-weight: bold;">Bs:</td>
											<td class="text-right"><strong>Bs {{moneda($cierre->efectivo_guardado_bs)}}</strong></td>
										</tr>
										<tr>
											<td style="font-weight: bold;">COP:</td>
											<td class="text-right"><strong>$ {{moneda($cierre->efectivo_guardado_cop)}}</strong></td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						
						{{-- TABLA CONSOLIDADA SOLO EN DÓLARES --}}
						<table class="table" style="margin-top: 30px;">
							<thead>
								<tr class="table-dark">
									<th colspan="6" style="background-color: #343a40; color: white;">
										<h3>CUADRE CONSOLIDADO EN DÓLARES (TODOS LOS TIPOS GLOBALES)</h3>
									</th>
								</tr>
								<tr class="table-dark">
									<th>Tipo</th>
									<th class="text-right">Saldo Inicial</th>
									<th class="text-right">Real</th>
									<th class="text-right">Digital</th>
									<th class="text-right">Diferencia</th>
									<th class="text-center">Estado</th>
								</tr>
							</thead>
							<tbody>
								{{-- EFECTIVO TOTAL (USD + BS convertido + COP convertido) --}}
								@php
									$tasa_bs = floatval($cierre->tasa ?? 0);
									$tasa_cop = floatval($cierre->tasacop ?? 0);
									// Evitar división por cero
									$tasa_bs = $tasa_bs > 0 ? $tasa_bs : 1;
									$tasa_cop = $tasa_cop > 0 ? $tasa_cop : 1;
								@endphp
								<tr>
									<td class="left"><strong>EFECTIVO TOTAL</strong></td>
									<td class="text-right">
										${{number_format(
											floatval($cuadre_consolidado['efectivo_usd']['inicial'] ?? 0) + 
											(floatval($cuadre_consolidado['efectivo_bs']['inicial'] ?? 0) / $tasa_bs) + 
											(floatval($cuadre_consolidado['efectivo_cop']['inicial'] ?? 0) / $tasa_cop)
										, 2)}}
									</td>
									<td class="text-right">
										<strong>${{number_format(
											floatval($cuadre_consolidado['efectivo_usd']['real'] ?? 0) + 
											(floatval($cuadre_consolidado['efectivo_bs']['real'] ?? 0) / $tasa_bs) + 
											(floatval($cuadre_consolidado['efectivo_cop']['real'] ?? 0) / $tasa_cop)
										, 2)}}</strong>
									</td>
									<td class="text-right">
										${{number_format(
											floatval($cuadre_consolidado['efectivo_usd']['digital'] ?? 0) + 
											(floatval($cuadre_consolidado['efectivo_bs']['digital'] ?? 0) / $tasa_bs) + 
											(floatval($cuadre_consolidado['efectivo_cop']['digital'] ?? 0) / $tasa_cop)
										, 2)}}
									</td>
									<td class="text-right">
										@php
											$efectivo_real_usd = floatval($cuadre_consolidado['efectivo_usd']['real'] ?? 0) + 
												(floatval($cuadre_consolidado['efectivo_bs']['real'] ?? 0) / $tasa_bs) + 
												(floatval($cuadre_consolidado['efectivo_cop']['real'] ?? 0) / $tasa_cop);
											$efectivo_digital_usd = floatval($cuadre_consolidado['efectivo_usd']['digital'] ?? 0) + 
												(floatval($cuadre_consolidado['efectivo_bs']['digital'] ?? 0) / $tasa_bs) + 
												(floatval($cuadre_consolidado['efectivo_cop']['digital'] ?? 0) / $tasa_cop);
											$efectivo_inicial_usd = floatval($cuadre_consolidado['efectivo_usd']['inicial'] ?? 0) + 
												(floatval($cuadre_consolidado['efectivo_bs']['inicial'] ?? 0) / $tasa_bs) + 
												(floatval($cuadre_consolidado['efectivo_cop']['inicial'] ?? 0) / $tasa_cop);
											$efectivo_diff_usd = $efectivo_real_usd - $efectivo_digital_usd - $efectivo_inicial_usd;
										@endphp
										<strong class="{{abs($efectivo_diff_usd) <= 5 ? 'text-success' : ($efectivo_diff_usd > 0 ? 'text-warning' : 'text-danger')}}">
											${{number_format($efectivo_diff_usd, 2)}}
										</strong>
									</td>
									<td class="text-center" style="@if(abs($efectivo_diff_usd) <= 5) background-color: #d4edda; @elseif($efectivo_diff_usd > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(abs($efectivo_diff_usd) <= 5)
											<span class="badge bg-success">CUADRADO</span>
										@elseif($efectivo_diff_usd > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
									</td>
								</tr>
								
								{{-- DÉBITO TOTAL (Pinpad + Otros Puntos convertidos a USD) --}}
								<tr>
									<td class="left"><strong>DÉBITO TOTAL</strong></td>
									<td class="text-right">-</td>
									<td class="text-right">
										<strong>${{number_format(
											(floatval($cuadre_consolidado['debito_pinpad']['real'] ?? 0) / $tasa_bs) + 
											(floatval($cuadre_consolidado['debito_otros']['real'] ?? 0) / $tasa_bs)
										, 2)}}</strong>
									</td>
									<td class="text-right">
										${{number_format(
											(floatval($cuadre_consolidado['debito_pinpad']['digital'] ?? 0) / $tasa_bs) + 
											(floatval($cuadre_consolidado['debito_otros']['digital'] ?? 0) / $tasa_bs)
										, 2)}}
									</td>
									<td class="text-right">
										@php
											$debito_real_usd = (floatval($cuadre_consolidado['debito_pinpad']['real'] ?? 0) / $tasa_bs) + 
												(floatval($cuadre_consolidado['debito_otros']['real'] ?? 0) / $tasa_bs);
											$debito_digital_usd = (floatval($cuadre_consolidado['debito_pinpad']['digital'] ?? 0) / $tasa_bs) + 
												(floatval($cuadre_consolidado['debito_otros']['digital'] ?? 0) / $tasa_bs);
											$debito_diff_usd = $debito_real_usd - $debito_digital_usd;
										@endphp
										<strong class="{{abs($debito_diff_usd) <= 5 ? 'text-success' : ($debito_diff_usd > 0 ? 'text-warning' : 'text-danger')}}">
											${{number_format($debito_diff_usd, 2)}}
										</strong>
									</td>
									<td class="text-center">
										@if(abs($debito_diff_usd) <= 5)
											<span class="badge bg-success">CUADRADO</span>
										@elseif($debito_diff_usd > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
									</td>
								</tr>
								
								{{-- TRANSFERENCIA --}}
								@if(isset($cuadre_consolidado['transferencia']))
								<tr>
									<td class="left"><strong>TRANSFERENCIA</strong></td>
									<td class="text-right">-</td>
									<td class="text-right"><strong>${{number_format(floatval($cuadre_consolidado['transferencia']['real'] ?? 0), 2)}}</strong></td>
									<td class="text-right">${{number_format(floatval($cuadre_consolidado['transferencia']['digital'] ?? 0), 2)}}</td>
									<td class="text-right text-success">
										<strong>${{number_format(floatval($cuadre_consolidado['transferencia']['diferencia'] ?? 0), 2)}}</strong>
									</td>
									<td class="text-center" style="background-color: #d4edda; font-weight: bold;">
										<span class="badge bg-success">CUADRADO</span>
									</td>
								</tr>
								@endif
								
								{{-- FILA DE TOTALES --}}
								@php
									// Calcular totales
									$total_inicial = $efectivo_inicial_usd; // Efectivo tiene inicial, débito y transferencia no
									
									$total_real = $efectivo_real_usd + $debito_real_usd + floatval($cuadre_consolidado['transferencia']['real'] ?? 0);
									
									$total_digital = $efectivo_digital_usd + $debito_digital_usd + floatval($cuadre_consolidado['transferencia']['digital'] ?? 0);
									
									$total_diferencia = $total_real - $total_digital - $total_inicial;
								@endphp
								<tr style="background-color: #e9ecef; font-weight: bold; border-top: 3px solid #343a40;">
									<td class="left"><strong>TOTAL</strong></td>
									<td class="text-right"><strong>${{(moneda($total_inicial))}}</strong></td>
									<td class="text-right"><strong>${{(moneda($total_real))}}</strong></td>
									<td class="text-right"><strong>${{(moneda($total_digital))}}</strong></td>
									<td class="text-right">
										<strong class="{{abs($total_diferencia) <= 5 ? 'text-success' : ($total_diferencia > 0 ? 'text-warning' : 'text-danger')}}">
											${{(moneda($total_diferencia))}}
										</strong>
									</td>
									<td class="text-center" style="@if(abs($total_diferencia) <= 5) background-color: #d4edda; @elseif($total_diferencia > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
										@if(abs($total_diferencia) <= 5)
											<span class="badge bg-success">CUADRADO</span>
										@elseif($total_diferencia > 0)
											<span class="badge bg-warning text-dark">SOBRAN</span>
										@else
											<span class="badge bg-danger">FALTAN</span>
										@endif
									</td>
				</tr>
							</tbody>
						</table>
						
						{{-- INFORMACIÓN DE DESCUENTOS --}}
						@php
							$precio_total = floatval($cierre->precio ?? 0); // Venta bruta (sin descuentos)
							$desc_total = floatval($cierre->desc_total ?? 0); // Venta neta (con descuentos)
							$monto_descuento = $precio_total - $desc_total; // Monto total de descuento
							$porcentaje_descuento = $precio_total > 0 ? ($monto_descuento / $precio_total) * 100 : 0; // Porcentaje de descuento
						@endphp
						<div style="margin-top: 30px; margin-bottom: 20px;">
							<h5 style="color: #1e3a8a; font-weight: bold; margin-bottom: 15px; text-align: center;">
								<i class="fa fa-percent"></i> Información de Descuentos
							</h5>
							<div style="display: flex; gap: 15px; justify-content: space-between;">
								{{-- Tabla 1: Venta Bruta --}}
								<table class="table" style="width: 25%; margin: 0; border: 2px solid #4a90e2;">
									<thead style="background-color: #e3f2fd;">
										<tr>
											<th colspan="2" style="text-align: center; color: #1565c0; font-weight: bold; border-bottom: 2px solid #4a90e2;">
												Venta Bruta
											</th>
				</tr>
									</thead>
									<tbody>
										<tr>
											<td class="text-center" style="font-weight: bold; padding: 8px;">
												<strong style="font-size: 18px;">${{number_format($precio_total, 2)}}</strong>
											</td>
										</tr>
									</tbody>
								</table>
								
								{{-- Tabla 2: Monto de Descuento --}}
								<table class="table" style="width: 25%; margin: 0; border: 2px solid #ff9800;">
									<thead style="background-color: #fff3e0;">
										<tr>
											<th colspan="2" style="text-align: center; color: #e65100; font-weight: bold; border-bottom: 2px solid #ff9800;">
												Monto de Descuento
											</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td class="text-center" style="font-weight: bold; padding: 8px;">
												<strong style="font-size: 18px; color: #e65100;">${{number_format($monto_descuento, 2)}}</strong>
											</td>
										</tr>
									</tbody>
								</table>
								
								{{-- Tabla 3: Porcentaje de Descuento --}}
								<table class="table" style="width: 25%; margin: 0; border: 2px solid #9c27b0;">
									<thead style="background-color: #f3e5f5;">
										<tr>
											<th colspan="2" style="text-align: center; color: #6a1b9a; font-weight: bold; border-bottom: 2px solid #9c27b0;">
												% de Descuento
					</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td class="text-center" style="font-weight: bold; padding: 8px;">
												<strong style="font-size: 18px; color: #6a1b9a;">{{number_format($porcentaje_descuento, 2)}}%</strong>
											</td>
										</tr>
									</tbody>
								</table>
								
								{{-- Tabla 4: Venta Neta --}}
								<table class="table" style="width: 25%; margin: 0; border: 2px solid #28a745;">
									<thead style="background-color: #d4edda;">
										<tr>
											<th colspan="2" style="text-align: center; color: #155724; font-weight: bold; border-bottom: 2px solid #28a745;">
												Venta Neta
					</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td class="text-center" style="font-weight: bold; padding: 8px;">
												<strong style="font-size: 18px; color: #155724;">${{number_format($desc_total, 2)}}</strong>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</td>
				</tr>
				@endif
				
				{{-- CUADRES INDIVIDUALES POR USUARIO (solo si es totalizar) --}}
				@if(($totalizarcierre ?? false) && isset($cuadres_individuales) && count($cuadres_individuales) > 0)
					@foreach($cuadres_individuales as $cuadre_individual)
					<tr>
						<td colspan="6">
							<h3 class="text-center">CUADRE INDIVIDUAL - {{$cuadre_individual['usuario']->usuario ?? 'N/A'}} ({{$cuadre_individual['usuario']->nombre ?? 'N/A'}})</h3>
							@if(isset($cuadre_individual['cuadre_detallado']) && $cuadre_individual['cuadre_detallado'])
								<table class="table" style="margin-top: 15px;">
									<thead>
										<tr class="table-dark">
											<th>Tipo</th>
											<th class="text-right">Saldo Inicial</th>
											<th class="text-right">Real</th>
											<th class="text-right">Digital</th>
											<th class="text-right">Diferencia</th>
											<th class="text-center">Estado</th>
				</tr>
									</thead>
									<tbody>
										{{-- EFECTIVO --}}
										<tr>
											<td colspan="6" style="background-color: #e3f2fd; font-weight: bold;">EFECTIVO</td>
										</tr>
										@if(isset($cuadre_individual['cuadre_detallado']['efectivo_usd']))
										<tr>
											<td class="left"><strong>Dólares (USD)</strong></td>
											<td class="text-right">{{number_format(floatval($cuadre_individual['cuadre_detallado']['efectivo_usd']['inicial'] ?? 0), 2)}}</td>
											<td class="text-right"><strong>${{number_format(floatval($cuadre_individual['cuadre_detallado']['efectivo_usd']['real'] ?? 0), 2)}}</strong></td>
											<td class="text-right">${{number_format(floatval($cuadre_individual['cuadre_detallado']['efectivo_usd']['digital'] ?? 0), 2)}}</td>
											<td class="text-right {{($cuadre_individual['cuadre_detallado']['efectivo_usd']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_individual['cuadre_detallado']['efectivo_usd']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
												<strong>${{number_format(floatval($cuadre_individual['cuadre_detallado']['efectivo_usd']['diferencia'] ?? 0), 2)}}</strong>
											</td>
											<td class="text-center" style="@if(($cuadre_individual['cuadre_detallado']['efectivo_usd']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_individual['cuadre_detallado']['efectivo_usd']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
												@if(($cuadre_individual['cuadre_detallado']['efectivo_usd']['estado'] ?? '') == 'cuadrado')
													<span class="badge bg-success">CUADRADO</span>
												@elseif(($cuadre_individual['cuadre_detallado']['efectivo_usd']['diferencia'] ?? 0) > 0)
													<span class="badge bg-warning text-dark">SOBRAN</span>
												@else
													<span class="badge bg-danger">FALTAN</span>
												@endif
											</td>
										</tr>
										@endif
										@if(isset($cuadre_individual['cuadre_detallado']['efectivo_bs']))
										<tr>
											<td class="left"><strong>Bolívares (Bs)</strong></td>
											<td class="text-right">{{moneda($cuadre_individual['cuadre_detallado']['efectivo_bs']['inicial'])}}</td>
											<td class="text-right"><strong>Bs {{moneda($cuadre_individual['cuadre_detallado']['efectivo_bs']['real'])}}</strong></td>
											<td class="text-right">Bs {{moneda($cuadre_individual['cuadre_detallado']['efectivo_bs']['digital'])}}</td>
											<td class="text-right {{($cuadre_individual['cuadre_detallado']['efectivo_bs']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_individual['cuadre_detallado']['efectivo_bs']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
												<strong>Bs {{moneda($cuadre_individual['cuadre_detallado']['efectivo_bs']['diferencia'])}}</strong>
											</td>
											<td class="text-center" style="@if(($cuadre_individual['cuadre_detallado']['efectivo_bs']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_individual['cuadre_detallado']['efectivo_bs']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
												@if(($cuadre_individual['cuadre_detallado']['efectivo_bs']['estado'] ?? '') == 'cuadrado')
													<span class="badge bg-success">CUADRADO</span>
												@elseif(($cuadre_individual['cuadre_detallado']['efectivo_bs']['diferencia'] ?? 0) > 0)
													<span class="badge bg-warning text-dark">SOBRAN</span>
												@else
													<span class="badge bg-danger">FALTAN</span>
												@endif
											</td>
										</tr>
										@endif
										@if(isset($cuadre_individual['cuadre_detallado']['efectivo_cop']))
										<tr>
											<td class="left"><strong>Pesos (COP)</strong></td>
											<td class="text-right">{{moneda($cuadre_individual['cuadre_detallado']['efectivo_cop']['inicial'])}}</td>
											<td class="text-right"><strong>$ {{moneda($cuadre_individual['cuadre_detallado']['efectivo_cop']['real'])}}</strong></td>
											<td class="text-right">$ {{moneda($cuadre_individual['cuadre_detallado']['efectivo_cop']['digital'])}}</td>
											<td class="text-right {{($cuadre_individual['cuadre_detallado']['efectivo_cop']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_individual['cuadre_detallado']['efectivo_cop']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
												<strong>$ {{moneda($cuadre_individual['cuadre_detallado']['efectivo_cop']['diferencia'])}}</strong>
											</td>
											<td class="text-center" style="@if(($cuadre_individual['cuadre_detallado']['efectivo_cop']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_individual['cuadre_detallado']['efectivo_cop']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
												@if(($cuadre_individual['cuadre_detallado']['efectivo_cop']['estado'] ?? '') == 'cuadrado')
													<span class="badge bg-success">CUADRADO</span>
												@elseif(($cuadre_individual['cuadre_detallado']['efectivo_cop']['diferencia'] ?? 0) > 0)
													<span class="badge bg-warning text-dark">SOBRAN</span>
												@else
													<span class="badge bg-danger">FALTAN</span>
												@endif
											</td>
										</tr>
										@endif
										
										{{-- DÉBITO --}}
										<tr>
											<td colspan="6" style="background-color: #fff3cd; font-weight: bold;">DÉBITO</td>
										</tr>
										@if(isset($cuadre_individual['cuadre_detallado']['debito_pinpad']))
										<tr>
											<td class="left"><strong>Pinpad</strong></td>
											<td class="text-right">-</td>
											<td class="text-right"><strong>Bs {{moneda($cuadre_individual['cuadre_detallado']['debito_pinpad']['real'])}}</strong></td>
											<td class="text-right">Bs {{moneda($cuadre_individual['cuadre_detallado']['debito_pinpad']['digital'])}}</td>
											<td class="text-right text-success">
												<strong>Bs {{moneda($cuadre_individual['cuadre_detallado']['debito_pinpad']['diferencia'])}}</strong>
											</td>
											<td class="text-center" style="background-color: #d4edda; font-weight: bold;">
												<span class="badge bg-success">CUADRADO</span>
											</td>
										</tr>
										@endif
										@if(isset($cuadre_individual['cuadre_detallado']['debito_otros']))
										<tr>
											<td class="left"><strong>Otros Puntos</strong></td>
											<td class="text-right">-</td>
											<td class="text-right"><strong>Bs {{moneda($cuadre_individual['cuadre_detallado']['debito_otros']['real'])}}</strong></td>
											<td class="text-right">Bs {{moneda($cuadre_individual['cuadre_detallado']['debito_otros']['digital'])}}</td>
											<td class="text-right {{($cuadre_individual['cuadre_detallado']['debito_otros']['estado'] ?? '') == 'cuadrado' ? 'text-success' : (($cuadre_individual['cuadre_detallado']['debito_otros']['diferencia'] ?? 0) > 0 ? 'text-warning' : 'text-danger')}}">
												<strong>Bs {{moneda($cuadre_individual['cuadre_detallado']['debito_otros']['diferencia'])}}</strong>
											</td>
											<td class="text-center" style="@if(($cuadre_individual['cuadre_detallado']['debito_otros']['estado'] ?? '') == 'cuadrado') background-color: #d4edda; @elseif(($cuadre_individual['cuadre_detallado']['debito_otros']['diferencia'] ?? 0) > 0) background-color: #fff3cd; @else background-color: #f8d7da; @endif font-weight: bold;">
												@if(($cuadre_individual['cuadre_detallado']['debito_otros']['estado'] ?? '') == 'cuadrado')
													<span class="badge bg-success">CUADRADO</span>
												@elseif(($cuadre_individual['cuadre_detallado']['debito_otros']['diferencia'] ?? 0) > 0)
													<span class="badge bg-warning text-dark">SOBRAN</span>
												@else
													<span class="badge bg-danger">FALTAN</span>
												@endif
											</td>
										</tr>
										@endif
										
										{{-- TRANSFERENCIA --}}
										@if(isset($cuadre_individual['cuadre_detallado']['transferencia']))
										<tr>
											<td colspan="6" style="background-color: #d1ecf1; font-weight: bold;">TRANSFERENCIA</td>
				</tr>
										<tr>
											<td class="left"><strong>Transferencia</strong></td>
											<td class="text-right">-</td>
											<td class="text-right"><strong>${{moneda($cuadre_individual['cuadre_detallado']['transferencia']['real'])}}</strong></td>
											<td class="text-right">${{moneda($cuadre_individual['cuadre_detallado']['transferencia']['digital'])}}</td>
											<td class="text-right text-success">
												<strong>${{moneda($cuadre_individual['cuadre_detallado']['transferencia']['diferencia'])}}</strong>
											</td>
											<td class="text-center" style="background-color: #d4edda; font-weight: bold;">
												<span class="badge bg-success">CUADRADO</span>
											</td>
										</tr>
										@endif
									</tbody>
								</table>
								
								{{-- Datos Introducidos por Usuario --}}
								<div style="margin-top: 15px; margin-bottom: 15px;">
									<h6 style="color: #495057; font-weight: bold; margin-bottom: 10px; text-align: center;">
										<i class="fa fa-keyboard"></i> Datos Introducidos
									</h6>
									<div style="display: flex; gap: 10px; justify-content: space-between;">
										{{-- Tabla 1: Efectivo Real Contado --}}
										<table class="table" style="width: 33.33%; margin: 0; border: 2px solid #90caf9; font-size: 12px;">
											<thead style="background-color: #e3f2fd;">
												<tr>
													<th colspan="2" style="text-align: center; color: #1565c0; font-weight: bold; border-bottom: 2px solid #90caf9; padding: 6px;">
														Efectivo Real
													</th>
				</tr>
											</thead>
											<tbody>
												<tr>
													<td style="font-weight: bold; padding: 4px;">USD:</td>
													<td class="text-right" style="padding: 4px;"><strong>${{moneda($cuadre_individual['cierre']->efectivo_actual)}}</strong></td>
												</tr>
												<tr>
													<td style="font-weight: bold; padding: 4px;">Bs:</td>
													<td class="text-right" style="padding: 4px;"><strong>Bs {{moneda($cuadre_individual['cierre']->efectivo_actual_bs)}}</strong></td>
												</tr>
												<tr>
													<td style="font-weight: bold; padding: 4px;">COP:</td>
													<td class="text-right" style="padding: 4px;"><strong>$ {{moneda($cuadre_individual['cierre']->efectivo_actual_cop)}}</strong></td>
												</tr>
											</tbody>
										</table>
										
										{{-- Tabla 2: Dejar en Caja --}}
										<table class="table" style="width: 33.33%; margin: 0; border: 2px solid #ffc107; font-size: 12px;">
											<thead style="background-color: #fff3cd;">
												<tr>
													<th colspan="2" style="text-align: center; color: #856404; font-weight: bold; border-bottom: 2px solid #ffc107; padding: 6px;">
														Dejar en Caja
					</th>
												</tr>
											</thead>
											<tbody>
												<tr>
													<td style="font-weight: bold; padding: 4px;">USD:</td>
													<td class="text-right" style="padding: 4px;"><strong>${{number_format(floatval($cuadre_individual['cierre']->dejar_dolar ?? 0), 2)}}</strong></td>
												</tr>
												<tr>
													<td style="font-weight: bold; padding: 4px;">Bs:</td>
													<td class="text-right" style="padding: 4px;"><strong>Bs {{number_format(floatval($cuadre_individual['cierre']->dejar_bss ?? 0), 2)}}</strong></td>
												</tr>
												<tr>
													<td style="font-weight: bold; padding: 4px;">COP:</td>
													<td class="text-right" style="padding: 4px;"><strong>$ {{number_format(floatval($cuadre_individual['cierre']->dejar_peso ?? 0), 2)}}</strong></td>
												</tr>
											</tbody>
										</table>
										
										{{-- Tabla 3: Efectivo Guardado --}}
										<table class="table" style="width: 33.33%; margin: 0; border: 2px solid #28a745; font-size: 12px;">
											<thead style="background-color: #d4edda;">
												<tr>
													<th colspan="2" style="text-align: center; color: #155724; font-weight: bold; border-bottom: 2px solid #28a745; padding: 6px;">
														Efectivo Guardado
													</th>
				</tr>
											</thead>
											<tbody>
												<tr>
													<td style="font-weight: bold; padding: 4px;">USD:</td>
													<td class="text-right" style="padding: 4px;"><strong>${{moneda($cuadre_individual['cierre']->efectivo_guardado)}}</strong></td>
												</tr>
												<tr>
													<td style="font-weight: bold; padding: 4px;">Bs:</td>
													<td class="text-right" style="padding: 4px;"><strong>Bs {{moneda($cuadre_individual['cierre']->efectivo_guardado_bs)}}</strong></td>
												</tr>
												<tr>
													<td style="font-weight: bold; padding: 4px;">COP:</td>
													<td class="text-right" style="padding: 4px;"><strong>$ {{moneda($cuadre_individual['cierre']->efectivo_guardado_cop)}}</strong></td>
												</tr>
											</tbody>
										</table>
									</div>
								</div>
							@endif
						</td>
					</tr>
					@endforeach
				@endif
				
			
				
				
				
				<tr>
					<th colspan="5">
						<h2>
							NOTA
						</h2>
						{{ ($cierre->nota) }}
					</th>
				</tr>
				<!-- Créditos y Abonos del Día -->
				<tr>
					<th>CRÉDITOS DEL DÍA</th>
					<td>{{ $facturado[4] }}</td>
					<th>CRÉDITO POR COBRAR TOTAL</th>
					<td><span class="h2">{{ number_format($cred_total, 2) }}</span></td>
				</tr>
				<tr>
					<th colspan="2">ABONOS DEL DÍA</th>
					<td colspan="2"><span class="h2">{{ number_format($abonosdeldia, 2) }}</span></td>
				</tr>
				
				@if(count($pedidos_abonos) > 0)
					<tr style="background: #e9ecef;">
						<th colspan="5" class="text-center">DETALLE DE ABONOS</th>
					</tr>
					<tr>
						<th style="width: 10%;"># Abono</th>
						<th style="width: 40%;">Cliente</th>
						<th style="width: 40%;">Detalle Pago</th>
						<th style="width: 10%;" colspan="2"></th>
					</tr>
				@endif
				@foreach ($pedidos_abonos as $e)
					<tr>
						<td>Abono #{{ $e->id }}</td>
						<td>{{ $e->cliente->nombre }}<br><small>{{ $e->cliente->identificacion }}</small></td>
						<td colspan="3">
							@foreach ($e->pagos as $ee)
								<span> 
									<b>
										@switch($ee->tipo)
											@case(1) Transferencia @break
											@case(2) Débito @break
											@case(3) Efectivo @break
											@case(4) Crédito @break
											@case(5) Otros @break
											@case(6) Vuelto @break
											@default {{ $ee->tipo }} @break
										@endswitch  
									</b>
									{{ number_format($ee->monto, 2) }}
								</span>
								@if (!$loop->last) <br> @endif
							@endforeach
						</td>
					</tr>
				@endforeach

				{{-- Resumen de pagos electrónicos por banco (Puntos de venta, Pinpad, Transferencia) --}}
				<tr>
					<th colspan="5" style="background-color: #e3f2fd;">RESUMEN PAGOS ELECTRÓNICOS POR BANCO</th>
				</tr>
				<tr class="table-dark">
					<th>BANCO</th>
					<th class="text-right">PUNTOS DE VENTA</th>
					<th class="text-right">PINPAD</th>
					<th class="text-right">TRANSFERENCIA</th>
					<th class="text-right">TOTAL BANCO</th>
				</tr>
				@if(isset($resumen_electronicos_por_banco) && count($resumen_electronicos_por_banco) > 0)
					@foreach ($resumen_electronicos_por_banco as $banco_nombre => $totales)
						@php
							$total_banco = ($totales['puntos_venta'] ?? 0) + ($totales['pinpad'] ?? 0) + ($totales['transferencia'] ?? 0);
						@endphp
						<tr>
							<td class="left"><strong>{{ $banco_nombre }}</strong></td>
							<td class="text-right">{{ number_format($totales['puntos_venta'] ?? 0, 2) }}</td>
							<td class="text-right">{{ number_format($totales['pinpad'] ?? 0, 2) }}</td>
							<td class="text-right">{{ number_format($totales['transferencia'] ?? 0, 2) }}</td>
							<td class="text-right"><strong>{{ number_format($total_banco, 2) }}</strong></td>
						</tr>
					@endforeach
				@else
					<tr>
						<td colspan="5" class="text-center">Sin datos de pagos electrónicos por banco.</td>
					</tr>
				@endif

				<tr>
					<th colspan="5">REFERENCIAS DE PAGOS ELECTRÓNICOS</th>

				</tr>
				<tr>
					<th>BANCO</th>
					<th>CONCEPTO</th>
					<th>MONTO</th>
					<th>PEDIDO</th>
				</tr>
				@foreach ($referencias as $e)
					<tr>
						<td>
							{{$e->banco}}
							@if ($e->tipo==1)
							Transferencia
							@endif
							@if ($e->tipo==2)
							Débito
							@endif
						</td>
						<th>{{$e->descripcion}}</th>
						<td>{{moneda($e->monto)}}</td>
						<td>Pedido #{{$e->id_pedido}} 
							<br>
							{{$e->created_at}}
						</td>
					</tr>
				@endforeach

				<tr>
					<th colspan="5">PUNTOS DE VENTA</th>

				</tr>
				<tr>
					<th>BANCO</th>
					<th>LOTE</th>
					<th colspan="2">MONTO</th>
				</tr>
				@foreach ($facturado["lotes"] as $e)
					<tr>
						<td>{{$e["banco"]}}</td>
						<th>{{$e["lote"]}}</th>
						<td colspan="2">{{$e["monto"]}}</td>
					</tr>
				@endforeach

				

				

				<tr>
					<th colspan="5">BIOPAGO</th>

				</tr>
				<tr>
					<th colspan="2">SERIAL</th>
					<th colspan="2">MONTO</th>
				</tr>
				@foreach ($facturado["biopagos"] as $e)
					<tr>
						<th colspan="2">{{$e["serial"]}}</th>
						<td colspan="2">{{$e["monto"]}}</td>
					</tr>
				@endforeach

				<tr>
					<td colspan="5">
						<table class="font-cuaderno">
							<tr>
								<td>
									<!-- <table class="text-left">
										<thead>
											<tr>
												<td colspan="2"><h4>RESUMEN</h4></td>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td colspan="2">
													<h3>{{$sucursal->sucursal}} {{$cierre->fecha}}</h3>
													
												</td>
											</tr>
							
											<tr><td class="right">NUM. VENTAS: </td><td class="left">  {{$facturado["numventas"]}}  </td></tr>
											<tr><td class="right">VENTA BRUTA TOTAL: </td><td class="left">  <b>{{($cierre_tot)}}</b>  </td></tr>
							
											<tr><td class="right">EFECTIVO: </td><td class="left">   <b>{{($cierre->efectivo)}}</b>  </td></tr>
											<tr><td class="right">DÉBITO: </td><td class="left">   <b>{{($cierre->debito)}}</b>  </td></tr>
											<tr><td class="right">TRANSFERENCIA: </td><td class="left">   <b>{{($cierre->transferencia)}}</b>  </td></tr>
											<tr><td class="right">BIOPAGO: </td><td class="left">   <b>{{($cierre->caja_biopago)}}</b>  </td></tr>
											<tr><td class="right">INVENTARIO: </td><td class="left">  BASE.  <b>{{$total_inventario_base_format}}</b> <br> VENTA. <b>{{$total_inventario_format}}</b>  </td></tr>
											<tr><td class="right">EFEC. GUARDADO $:</td><td class="left"> <b>{{($cierre->efectivo_guardado)}}</b>  </td></tr>
											<tr><td class="right">EFEC. GUARDADO BS:</td><td class="left"> <b>{{($cierre->efectivo_guardado_bs)}}</b>  </td></tr>
											<tr><td class="right">EFEC. GUARDADO PESO:</td><td class="left"> <b>{{($cierre->efectivo_guardado_cop)}}</b>  </td></tr>
											
											<tr>
												<td class="right">TASA:</td>
												<td class="left"><b>BS/$ {{($cierre->tasa)}} </b> / <b>COP/$ {{($cierre->tasacop)}} </b></td>
											</tr>
											<tr>
												<td class="right">CAJA INICIAL:</td>
												<td class="left">{{$facturado["caja_inicial"]}} <b></b></td>
											</tr>
											<tr>
												<td class="right">DEJAR EN CAJA:</td>
												<td class="left">
													$ = <b>{{($cierre->dejar_dolar)}}</b> /
													
													
													COP = <b>{{($cierre->dejar_peso)}}</b> /
													
													
													BSS = <b>{{($cierre->dejar_bss)}}</b>
												</td>
											</tr>
										</tbody>
									</table> -->
								</td>
								<td>
									@php
										// Helper para mostrar los métodos de pago en letras
										function metodoPagoNombre($tipo) {
											$map = [
												'1' => 'Transferencia',
												'2' => 'Débito',
												'3' => 'Efectivo',
												'4' => 'Crédito',
												'5' => 'Otros',
												'6' => 'Vuelto',
											];
											return $map[$tipo] ?? 'Desconocido';
										}
									@endphp
									<table>
										<thead>
											<tr>
												<td colspan="7">
													<h4>DEVOLUCIONES DEL DÍA</h4>
													@if(isset($devoluciones) && $devoluciones['count_pedidos'] > 0)
														<p style="margin: 5px 0;">
															<strong>{{$devoluciones['count_pedidos']}} pedidos</strong> con 
															<strong>{{$devoluciones['count_total']}} items</strong> devueltos
														</p>
													@endif
												</td>
											</tr>
										</thead>
										<tbody>
											@if(isset($devoluciones) && $devoluciones['count_total'] > 0)
												@foreach($devoluciones['pedidos'] as $pedido)
													<!-- Encabezado del Pedido -->
													<tr style="background-color: #e8e8e8;">
														<th colspan="7" class="left" style="padding: 8px;">
															<div style="display: flex; justify-content: space-between; align-items: center;">
																<div>
																	<strong>PEDIDO #{{$pedido['pedido_id']}}</strong> - 
																	{{$pedido['fecha']}} - 
																	Vendedor: {{$pedido['vendedor']}} ({{$pedido['vendedor_nombre']}})
																</div>
																<div>
																	<strong>Total Pedido: ${{number_format($pedido['total_pedido'], 2)}}</strong>
																</div>
															</div>
														</th>
													</tr>

													<!-- Items del Pedido -->
													<tr style="background-color: #f5f5f5;">
														<th style="width: 80px;">Tipo</th>
														<th style="width: 100px;">Código</th>
														<th>Producto</th>
														<th style="width: 60px;">Cant.</th>
														<th style="width: 80px;">P/U</th>
														<th style="width: 90px;">Total</th>
													</tr>
													@foreach($pedido['items'] as $item)
														<tr class="{{ $item->es_bueno ? 'bg-success-light' : 'bg-danger-light' }}">
															<td>
																@if($item->cantidad < 0)
																	<span style="color: #d9534f; font-weight: bold;">⬇ Salida</span><br>
																@else
																	<span style="color: #5cb85c; font-weight: bold;">⬆ Entrada</span><br>
																@endif
																@if($item->es_bueno)
																	<small style="color: green;">✓ Bueno</small>
																@else
																	<small style="color: red;">✗ Garantía</small>
																@endif
															</td>
															<td>{{$item->codigo_barras}}</td>
															<td class="left">{{$item->descripcion}}</td>
															<td style="color: {{ $item->cantidad < 0 ? '#d9534f' : '#5cb85c' }}; font-weight: bold;">{{$item->cantidad}}</td>
															<td>${{number_format($item->precio_unitario, 2)}}</td>
															<td><strong style="color: {{ $item->total_devolucion < 0 ? '#d9534f' : '#5cb85c' }};">${{number_format($item->total_devolucion, 2)}}</strong></td>
														</tr>
													@endforeach

													<!-- Pagos Devueltos -->
													@if(count($pedido['pagos_devueltos']) > 0)
														<tr>
															<th colspan="7" style="background-color: #fff3cd; padding: 5px;" class="left">
																<strong>💰 PAGOS:</strong>
																@foreach($pedido['pagos_devueltos'] as $pago)
																	<span style="margin-left: 15px;">
																		<strong>{{ metodoPagoNombre($pago['tipo']) }}</strong>: 
																		<span style="color: {{ $pago['monto'] < 0 ? 'red' : 'green' }};">${{number_format($pago['monto'], 2)}}</span>
																	</span>
																@endforeach
															</th>
														</tr>
													@else
														<tr>
															<th colspan="7" style="background-color: #f8f9fa; padding: 5px; font-weight: normal; font-style: italic;" class="left">
																Sin pagos devueltos registrados
															</th>
														</tr>
													@endif

													<!-- Totales del Pedido -->
													<tr style="background-color: #d4d4d4;">
														<th colspan="5" class="right">Subtotales del Pedido:</th>
														<th colspan="2" class="left">
															@if($pedido['total_buenos'] > 0)
																<span style="color: green;">Buenos: ${{number_format($pedido['total_buenos'], 2)}}</span>
															@endif
															@if($pedido['total_malos'] > 0)
																<span style="color: red; margin-left: 10px;">Garantías: ${{number_format($pedido['total_malos'], 2)}}</span>
															@endif
														</th>
													</tr>

													<!-- Separador entre pedidos -->
													<tr style="height: 10px;">
														<td colspan="7" style="border: none;"></td>
													</tr>
												@endforeach

												<!-- TOTALES GENERALES -->
												<tr class="table-dark">
													<th colspan="7">
														<h4 style="margin: 10px 0;">RESUMEN GENERAL DE DEVOLUCIONES</h4>
													</th>
												</tr>
												<tr class="text-success">
													<th colspan="5" class="right">Total Devoluciones Buenas ({{$devoluciones['count_buenos']}} items):</th>
													<th colspan="2" class="left"><strong>{{$devoluciones['total_buenos']}}</strong></th>
												</tr>
												<tr class="text-danger">
													<th colspan="5" class="right">Total Garantías ({{$devoluciones['count_malos']}} items):</th>
													<th colspan="2" class="left"><strong>{{$devoluciones['total_malos']}}</strong></th>
												</tr>
												<tr class="table-dark">
													<th colspan="5" class="right"><h4>TOTAL GENERAL DEVOLUCIONES:</h4></th>
													<th colspan="2" class="left"><h4>{{$devoluciones['total_general']}}</h4></th>
												</tr>
											@else
												<tr>
													<td colspan="7" class="text-center">
														<p>No hay devoluciones registradas para este día.</p>
													</td>
												</tr>
											@endif
										</tbody>
									</table>
									<br/>
									<table>
										<tr><td><h5>CAJA FUERTE.</h5> </td></tr>
										<tr>
											<td>
												<table class="table">
														<tr>
															<th>TIPO</th>
															<th>Descripción</th>
															<th>Categoría</th>
															<th class="text-right">Monto DOLAR</th>
															<th class="">Balance DOLAR</th>
															<th rowspan="100"></th>
															<th class="text-right">Monto BS</th>
															<th class="">Balance BS</th>
															<th rowspan="100"></th>
															<th class="text-right">Monto PESO</th>
															<th class="">Balance PESO</th>
				
															<th class="text-right">Monto EURO</th>
															<th class="">Balance EURO</th>
															<th rowspan="100"></th>
															<th>HORA</th>
														</tr>
														@foreach ($cajas["detalles"]["fuerte"] as $e)
														<tr>
															<td class="">
																<small class="text-muted">
																	{{$e->tipo==0?"Caja Chica":null}}
																	{{$e->tipo==1?"Caja Fuerte":null}}
																</small>
															</td>
															<td class="">{{$e->concepto}}</td>
															<td class="">{{$e->cat?$e->cat->nombre:null}}</td>
															<td class={{($e->montodolar<0? "text-danger": " text-success")." text-right"}}>@if ($e->montodolar!="0"){{number_format($e->montodolar)}}@endif</td>
															<td class="@if ((isset($cajas["detalles"]["fuerte"][0]['id'])?$cajas["detalles"]["fuerte"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->dolarbalance)}}</td>
															
															<td class={{($e->montobs<0? "text-danger": " text-success")." text-right"}}>@if ($e->montobs!="0"){{number_format($e->montobs)}}@endif</td>
															<td  class="@if ((isset($cajas["detalles"]["fuerte"][0]['id'])?$cajas["detalles"]["fuerte"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->bsbalance)}}</td>
															
															<td class={{($e->montopeso<0? "text-danger": " text-success")." text-right"}}>@if ($e->montopeso!="0"){{number_format($e->montopeso)}}@endif</td>
															<td  class="@if ((isset($cajas["detalles"]["fuerte"][0]['id'])?$cajas["detalles"]["fuerte"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->pesobalance)}}</td>
				
				
															<td class={{($e->montoeuro<0? "text-danger": " text-success")." text-right"}}>@if ($e->montoeuro!="0"){{number_format($e->montoeuro)}}@endif</td>
															<td  class="@if ((isset($cajas["detalles"]["fuerte"][0]['id'])?$cajas["detalles"]["fuerte"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->eurobalance)}}</td>
															<td>{{$e->created_at}}</td>
														</tr>
														@endforeach
													</table>
												</td>
											</tr>
											<tr><td><h5>CAJA CHICA</h5> </td></tr>
											<tr>
												<td>
													<table class="table">
														<tr>
															<th>TIPO</th>
															<th>Descripción</th>
															<th>Categoría</th>
															<th class="text-right">Monto DOLAR</th>
															<th class="">Balance DOLAR</th>
															<th rowspan="100"></th>
															<th class="text-right">Monto BS</th>
															<th class="">Balance BS</th>
															<th rowspan="100"></th>
															<th class="text-right">Monto PESO</th>
															<th class="">Balance PESO</th>
															<th class="text-right">Monto EURO</th>
															<th class="">Balance EURO</th>
															<th rowspan="100"></th>
															<th>HORA</th>
														</tr>
														@foreach ($cajas["detalles"]["chica"] as $e)
														<tr>
															<td class="">
																<small class="text-muted">
																	{{$e->tipo==0?"Caja Chica":null}}
																	{{$e->tipo==1?"Caja Fuerte":null}}
																</small>
															</td>
															<td class="">{{$e->concepto}}</td>
															<td class="">{{$e->cat?$e->cat->nombre:null}}</td>
															
															<td class={{($e->montodolar<0? "text-danger": " text-success")." text-right"}}>{{number_format($e->montodolar,2)}}</td>
															<td  class="@if ((isset($cajas["detalles"]["chica"][0]['id'])?$cajas["detalles"]["chica"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->dolarbalance,2)}}</td>
															
															<td class={{($e->montobs<0? "text-danger": " text-success")." text-right"}}>{{number_format($e->montobs,2)}}</td>
															<td  class="@if ((isset($cajas["detalles"]["chica"][0]['id'])?$cajas["detalles"]["chica"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->bsbalance,2)}}</td>
															
															<td class={{($e->montopeso<0? "text-danger": " text-success")." text-right"}}>{{number_format($e->montopeso,2)}}</td>
															<td  class="@if ((isset($cajas["detalles"]["chica"][0]['id'])?$cajas["detalles"]["chica"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->pesobalance,2)}}</td>
				
															<td class={{($e->montoeuro<0? "text-danger": " text-success")." text-right"}}>{{number_format($e->montoeuro,2)}}</td>
															<td  class="@if ((isset($cajas["detalles"]["chica"][0]['id'])?$cajas["detalles"]["chica"][0]['id']:0)==$e->id)
																text-warning
															@endif">{{number_format($e->eurobalance,2)}}</td>
															<td>{{$e->created_at}}</td>
														</tr>
														@endforeach
													</table>
												</td>
											</tr>
									</table>
				
								</td>
							</tr>
						</table>
					</td>
				</tr>
				{{-- <tr>
					<th colspan="5">MOVIMIENTOS DE CAJA</th>
				
				</tr>
				<tr>
					<th colspan="2">ENTREGADO</th>
					<th colspan="3">PENDIENTE</th>
				</tr>
				@foreach($facturado["entre_pend_get"] as $fila)
				

					@if($fila["tipo"]==1)
						<tr>
							<td>{{$fila["monto"]}} <b></b></td>
							<th>{{$fila["descripcion"]}} <br>{{$fila["created_at"]}}</th>
							<th colspan='3'></th>
						</tr>
					@else
						<tr>
							<th></th>
							<th></th>
							<td>{{ $fila["monto"] }} <b></b></td>
							<th colspan='1'>{{ $fila["descripcion"] }}</th>
							<th>{{ $fila["created_at"] }}</th>
						</tr>
					@endif
				@endforeach

				@foreach($vueltos_des as $val)

					<tr>
						<th></th>
						<th></th>
						<td>{{ $val["monto"] }} <b></b></td>
						<th colspan='2'>Ped.{{ $val["id_pedido"] }} Cliente: {{$val->cliente->cliente->identificacion}}
							<br/>
							{{ $val["updated_at"] }}
						</th>
					</tr>
				@endforeach --}}
				
				
			</tbody>
		</table>
		<hr/>
		
		<table class="table">
			<thead>
				<tr>
					<th colspan="9">
						MOVIMIENTOS DE INVENTARIO
					</th>
				</tr>
			  <tr>
				<th class="pointer">Usuario</th>
				<th class="pointer">Origen</th>
				<th class="pointer">Alterno</th>
				<th class="pointer">Barras</th>
				<th class="pointer">Descripción</th>
				<th class="pointer">Cantidad</th>
				<th class="pointer">Base</th>
				<th class="pointer">Venta</th>
				<th class="pointer">Hora</th>
			  </tr>
			</thead>
				@foreach ($movimientosInventario as $e)
					<tbody>
						<tr>
							<td rowSpan="2" class='align-middle'>{{isset($e->usuario)?$e->usuario->usuario:""}}</td>
							<td rowSpan="2" class='align-middle'>{{isset($e->origen)?$e->origen:""}}</td>
							@if ($e->antes)
								<td class="bg-danger-light">{{isset($e->antes->codigo_proveedor)?$e->antes->codigo_proveedor:""}}</td>
								<td class="bg-danger-light">{{isset($e->antes->codigo_barras)?$e->antes->codigo_barras:""}}</td>
								<td class="bg-danger-light">{{isset($e->antes->descripcion)?$e->antes->descripcion:""}}</td>
								<td class="bg-danger-light">{{isset($e->antes->cantidad)?$e->antes->cantidad:""}}</td>
								<td class="bg-danger-light">{{isset($e->antes->precio_base)?$e->antes->precio_base:""}}</td>
								<td class="bg-danger-light">{{isset($e->antes->precio)?$e->antes->precio:""}}</td>
							@else
								<td colSpan="6" class='text-center h4'>
								Producto nuevo
								</td>
							@endif
							
							<td>{{isset($e->created_at)?$e->created_at:""}}</td>
						</tr>
						<tr>
							@if ($e->despues)
								<td class="bg-success-light">{{isset($e->despues->codigo_proveedor)?$e->despues->codigo_proveedor:""}}</td>
								<td class="bg-success-light">{{isset($e->despues->codigo_barras)?$e->despues->codigo_barras:""}}</td>
								<td class="bg-success-light">{{isset($e->despues->descripcion)?$e->despues->descripcion:""}}</td>
								<td class="bg-success-light">{{isset($e->despues->cantidad)?$e->despues->cantidad:""}}</td>
								<td class="bg-success-light">{{isset($e->despues->precio_base)?$e->despues->precio_base:""}}</td>
								<td class="bg-success-light">{{isset($e->despues->precio)?$e->despues->precio:""}}</td>
							@else
								<td colSpan="6" class='text-center h4'>
								Producto Eliminado
								</td>
							@endif
							<td>{{isset($e->created_at)?$e->created_at:""}}</td>
						</tr>
					</tbody>
				@endforeach
		  </table>
		<table class="font-cuaderno">
			<tbody>
				<tr>
					<th colspan="7">MOVIMIENTOS DE PEDIDOS</th>
				</tr>
					@foreach($movimientos as $val)
						@if ($val->motivo)
							<tr>
								<td>{{$val->usuario?$val->usuario->usuario:null}}</td>
								<td>{{$val->tipo}}</td>
								<td><b>Motivo</b><br/>{{$val->motivo}}</td>
								<td><b>{{$val->tipo_pago?"MétodoDePago":"Sin pago"}}</b><br/>{{$val->tipo_pago}}</td>
								<td><b>Monto</b><br/>{{$val->monto}}</td>
								<td>
									<b>Items {{count($val->items)}}</b>
									
									<br/>
									@foreach ($val->items as $item)
										@if ($item->producto)
											{{$item->producto->codigo_barras}} <br>
										@endif
									@endforeach
								</td>
								<td>{{$val->created_at}}</td>
							</tr>

						@else	
							@foreach ($val->items as $e)
								<tr>
									<td>
										@if ($e->tipo==1)
											Entrada de Producto
										@endif
										@if ($e->tipo==0)
											Salida de Producto
										@endif

										@if ($e->tipo==2)
											{{$e->categoria}}
										@endif
									</td>
									<td>
										<b>Motivo</b><br/>
										@if ($e->categoria==1)
											Garantía
										@elseif ($e->categoria==2)
											Cambio
										@else
										{{$e->categoria}}
										@endif

									</td>
									@if (!$e->producto)
										<td><b>Producto/Desc.</b><br/> {{$e->descripcion}}</td>
										<td>P/U. {{$e->precio}}</td>
									@else
										<td><b>Producto</b><br/> {{$e->producto->descripcion}}</td>
										<td>P/U. {{$e->producto->precio}}</td>
									@endif
									<td>Ct. {{$e->cantidad}}</td>
									<td>{{$e->created_at}}</td>
								</tr>
							@endforeach
						@endif
					@endforeach
				
			</tbody>
		</table>
	</div>
	
	
	
</body>
</html>