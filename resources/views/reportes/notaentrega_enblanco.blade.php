<?php $t_base = 0; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.ico') }}">
    <title>Nota de Despacho {{	implode("-",$ids)	}}</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
	<style>
		@media print {
			.pagebreak { page-break-before: always; }
			@page {
				size: letter;
				margin: 0.25in;
			}
		}
		
		.delivery-note {
			font-family: Arial, sans-serif;
			max-width: 100%;
			width: 8.5in;
			margin: 0 auto;
			padding: 0.25in;
			box-sizing: border-box;
			font-size: 9px;
		}
		
		.delivery-header {
			text-align: center;
			margin-bottom: 0.1in;
			border-bottom: 1px solid #333;
			padding-bottom: 0.05in;
		}
		
		.delivery-title {
			font-size: 14px;
			font-weight: bold;
			margin-bottom: 0.02in;
		}
		
		.client-info {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 0.25in;
			margin-bottom: 0.25in;
		}
		
		.client-section {
			border: 1px solid #ddd;
			padding: 0.15in;
			border-radius: 5px;
		}
		
		.client-section h4 {
			margin: 0 0 0.1in 0;
			color: #333;
			font-size: 10px;
		}
		
		.client-field {
			margin-bottom: 0.05in;
		}
		
		.client-label {
			font-weight: bold;
			color: #555;
		}
		
		.products-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 0.25in;
		}
		
		.products-table th {
			background-color: #f8f9fa;
			border: 1px solid #ddd;
			padding: 6px 4px;
			text-align: center;
			font-weight: bold;
			font-size: 9px;
		}
		
		.products-table td {
			border: 1px solid #ddd;
			padding: 4px 4px;
			text-align: center;
			font-size: 8px;
		}
		
		.products-table .product-description {
			text-align: left;
			max-width: 200px;
		}
		
		.products-table .quantity {
			text-align: center;
			font-weight: bold;
		}
		
		.totals-section {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 0.75in;
			margin-bottom: 0.5in;
		}
		
		.totals-table {
			width: 100%;
			border-collapse: collapse;
			border: 2px solid #333;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		
		.totals-table th,
		.totals-table td {
			border: 1px solid #ddd;
			padding: 6px 8px;
			text-align: right;
			font-size: 9px;
		}
		
		.totals-table th {
			background-color: #f8f9fa;
			font-weight: bold;
			color: #333;
			border-bottom: 2px solid #333;
		}
		
		.totals-table tr:nth-child(even) {
			background-color: #f9f9f9;
		}
		
		.totals-table tr:hover {
			background-color: #f0f0f0;
		}
		
		.total-row {
			font-weight: bold;
			font-size: 12px;
			background-color: #e9ecef !important;
			border-top: 2px solid #333;
		}
		
		.total-row th,
		.total-row td {
			color: #333;
			font-weight: bold;
		}
		
		.signatures-section {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 1in;
			margin-top: 1in;
		}
		
		.signature-box {
			border-top: 1px solid #333;
			padding-top: 10px;
			text-align: center;
		}
		
		.signature-label {
			font-weight: bold;
			margin-bottom: 15px;
			font-size: 9px;
		}
		
		.notes-section {
			margin-top: 0.5in;
			border: 1px solid #ddd;
			padding: 0.25in;
			border-radius: 5px;
		}
		
		.notes-title {
			font-weight: bold;
			margin-bottom: 6px;
			font-size: 9px;
		}
		
		.status-badge {
			display: inline-block;
			padding: 4px 8px;
			border-radius: 4px;
			font-size: 12px;
			font-weight: bold;
			margin-left: 10px;
		}
		
		.status-anulado {
			background-color: #dc3545;
			color: white;
		}
		
		.status-pendiente {
			background-color: #ffc107;
			color: black;
		}
	</style>
</head>
<body>

	@foreach ($pedidos as $pedido)
		
		<div class="delivery-note">
			<div class="delivery-header">
				<div class="delivery-title">
					@if($pedido->estado==2)
						<span class="status-badge status-anulado">ANULADO</span>
					@endif
					@if($pedido->estado==0)
						<span class="status-badge status-pendiente">PENDIENTE</span>
					@endif
				</div>
				<div style="font-size: 10px; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
					<span>N° {{sprintf("%08d", $pedido->id)}}</span>
					<span style="font-size: 8px; color: #666;">Fecha: {{substr($pedido->created_at,0,10)}}</span>
				</div>
			</div>
			
			<div class="client-info">
				<div class="client-section">
					<h4>DATOS DEL CLIENTE</h4>
					<div class="client-field">
						<span class="client-label">Nombre/Razón Social:</span><br>
						{{ $pedido->cliente->nombre }}
					</div>
					<div class="client-field">
						<span class="client-label">RIF/C.I./Pasaporte:</span><br>
						{{ $pedido->cliente->identificacion }}
					</div>
					<div class="client-field">
						<span class="client-label">Dirección:</span><br>
						{{ $pedido->cliente->direccion }}
					</div>
				</div>
				
				<div class="client-section">
					<div class="client-field">
						@foreach($pedido->pagos as $ee)
							@if($ee->tipo==1&&$ee->monto!=0)<span class="status-badge" style="background-color: #17a2b8;">Trans. {{$ee->monto}}</span> @endif
							@if($ee->tipo==2&&$ee->monto!=0)<span class="status-badge" style="background-color: #6c757d;">Deb. {{$ee->monto}}</span> @endif
							@if($ee->tipo==3&&$ee->monto!=0)<span class="status-badge" style="background-color: #28a745;">Efec. {{$ee->monto}}</span> @endif
							@if($ee->tipo==6&&$ee->monto!=0)<span class="status-badge" style="background-color: #dc3545;">Vuel. {{$ee->monto}}</span> @endif
							@if($ee->tipo==4&&$ee->monto!=0)<span class="status-badge" style="background-color: #ffc107; color: black;">Cred. {{$ee->monto}}</span> @endif
						@endforeach
					</div>
					@if(isset($sucursal))
					<div class="client-field">
						<span class="client-label">Sucursal:</span><br>
						{{$sucursal->sucursal}}
					</div>
					@endif
				</div>
			</div>

			<table class="products-table">
				<thead>
					<tr>
						<th style="width: 20%;">Código</th>
						<th style="width: 45%;">Descripción del Producto</th>
						<th style="width: 15%;">Cantidad</th>
						<th style="width: 20%;">Precio Unitario</th>
						@if(isset($_GET["admin"]))
							<th style="width: 15%;">Precio Base</th>
						@endif
						<th style="width: 20%;">Subtotal</th>
						@if(isset($_GET["admin"]))
							<th style="width: 15%;">Subtotal Base</th>
						@endif
					</tr>
				</thead>
				<tbody>
					@php
						$sumBase = 0;
					@endphp
					@foreach ($pedido->items as $val)
						<tr>
							<td>{{$val->producto->codigo_barras}}</td>
							<td class="product-description">{{$val->producto->descripcion}}</td>
							<td class="quantity">{{number_format($val->cantidad, 2)}}</td>
							<td>
								@if ($bs)
									{{moneda($val->producto->precio*$bs)}}
								@else
									{{moneda($val->producto->precio)}}
								@endif
							</td>
							@if(isset($_GET["admin"]))
								<td>{{moneda($val->producto->precio_base)}}</td>
							@endif
							<td>
								@if ($bs)
									{{moneda($val->cantidad*$val->producto->precio*$bs)}}
								@else
									{{moneda($val->cantidad*$val->producto->precio)}}
								@endif
							</td>
							@if(isset($_GET["admin"]))
								@php
									$baseSub = $val->cantidad*$val->producto->precio_base;
									$sumBase += $baseSub;
								@endphp
								<td>{{moneda($baseSub)}}</td>
							@endif
						</tr>
					@endforeach
				</tbody>
			</table>

			<div class="totals-section">
				<div class="notes-section">
					<div class="notes-title">Observaciones:</div>
					<div style="min-height: 80px; border: 1px solid #eee; padding: 10px; background-color: #f9f9f9;">
						<!-- Espacio para observaciones -->
					</div>
				</div>
				
				<table class="totals-table">
					<tr>
						<th>SubTotal Venta</th>
						<td>
							@if ($bs)
								{{moneda(removemoneda($pedido->subtotal)*$bs)}}
							@else
								{{moneda($pedido->subtotal)}}
							@endif
						</td>
					</tr>
					<tr>
						<th>Descuento {{$pedido->total_porciento}}%</th>
						<td>
							@if ($bs)
								{{moneda(removemoneda($pedido->total_des)*$bs)}}
							@else
								{{moneda($pedido->total_des)}}
							@endif
						</td>
					</tr>
					<tr>
						<th>Monto Exento</th>
						<td>
							@if ($bs)
								{{moneda(removemoneda($pedido->exento)*$bs)}}
							@else
								{{moneda($pedido->exento)}}
							@endif
						</td>
					</tr>
					<tr>
						<th>Monto Gravable</th>
						<td>
							@if ($bs)
								{{moneda(removemoneda($pedido->gravable)*$bs)}}
							@else
								{{moneda($pedido->gravable)}}
							@endif
						</td>
					</tr>
					<tr>
						<th>IVA ({{$pedido->ivas}})</th>
						<td>
							@if ($bs)
								{{moneda(removemoneda($pedido->monto_iva)*$bs)}}
							@else
								{{moneda($pedido->monto_iva)}}
							@endif
						</td>
					</tr>
					@if(isset($_GET["admin"]))
						<tr>
							<th>TOTAL BASE</th>
							<td>{{moneda($sumBase)}}</td>
						</tr>
					@endif
					<tr class="total-row">
						<th>TOTAL</th>
						<td>
							@if ($bs)
								{{moneda(removemoneda($pedido->total)*$bs)}}
							@else
								{{moneda($pedido->total)}}
							@endif
						</td>
					</tr>
				</table>
			</div>

			<div class="signatures-section">
				<div class="signature-box">
					<div class="signature-label">Firma del Cliente</div>
				</div>
				<div class="signature-box">
					<div class="signature-label">Firma del Despachador</div>
				</div>
			</div>
		</div>
		
		@if(!$loop->last)
			<div class="pagebreak"></div>
		@endif
	@endforeach

</body>
</html> 