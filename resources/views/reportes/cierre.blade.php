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
					</td>
				</tr>
				<tr>
					<td>
						@if (isset($message))
							<img src="{{$message->embed('images/logo-small.jpg')}}" width="100px" >
						@else
							<img src="{{asset('images/logo-small.jpg')}}" width="100px" >
						@endif
						

						<h5>
							{{$sucursal->nombre_registro}}
							<br>
							<b>RIF. {{$sucursal->rif}}</b>
						</h5>
						
						<hr>
						<span>
							Domicilio Fiscal: {{$sucursal->direccion_registro}}
						</span>

					</td>
					<td colspan="2">
						<h2>CAJA</h2>
						$ = <b>{{($cierre->dejar_dolar)}}</b> /
						
						
						COP = <b>{{($cierre->dejar_peso)}}</b> /
						
						
						BSS = <b>{{($cierre->dejar_bss)}}</b>
					</td>
					<td>
						<h2>TASA</h2>
						{{($cierre->tasa)}}
					</td>
					<td>
						<h2>{{$cierre->fecha}}</h2>
						<h4>CAJERO: {{$cierre->usuario->usuario}}</h4>
					</td>
				</tr>
				<tr class="">
					<th class="right">
						VENTAS DEL DÍA
					</th>
					<td class=""><span class="text-success-only fs-3">{{($facturado["numventas"])}}</span></td>
					<th class="right">
						INVENTARIO
					</th>
					<td colspan="" class="left">Venta: {{$total_inventario_format}}<br>Base: {{$total_inventario_base_format}}</td>

					{{-- <td>
						<b>VUELTOS TOTALES</b> <hr>
						{{($vueltos_totales)}}
					</td> --}}
					<th class="@if($cierre->descuadre<-1 || $cierre->descuadre > 10) bg-danger-light @else bg-success-light @endif" rowspan="7">
						
						@if($cierre->descuadre<-1 || $cierre->descuadre > 10) <h3>DESCUADRADO</h3> @else <h3>CUADRADO</h3> @endif
						<h1>{{$cierre->descuadre}}</h1>
					</th>

					<th class="d-flex" rowspan="7">
						<h3>EFECTIVO GUARDADO:</h3>
						<span class="">$ <span class="fs-3">{{($cierre->efectivo_guardado)}}</span></span><br>
						<span class="">COP <span class="fs-3">{{($cierre->efectivo_guardado_cop)}}</span></span><br>
						<span class="">BS <span class="fs-3">{{($cierre->efectivo_guardado_bs)}}</span></span><br>
						
					</th>
				</tr>
				
				
				<tr>
					<th>DÉBITO</th>
					<th>EFECTIVO</th>
					<th>TRANSFERENCIA</th>
					<th>BIOPAGO</th>
					

				</tr>
				<tr>
					<td>{{($facturado[2])}}</td>
					<td>{{($facturado[3])}}</td>
					<td>{{($facturado[1])}}</td>
					<td>{{($facturado[5])}}</td>
				</tr>
				

				<tr class="text-success">
					<th colspan="2">
						<h3>FACTURADO DIGITAL</h3>
					</th>
					<th colspan="2">
						<h1 class="">{{($facturado_tot)}}</h1>
					</th>
					
				</tr>
				
				<tr class="border-top">
					<th class="">DÉBITO</th>
					<th class="">EFECTIVO</th>
					<th class="">TRANSFERENCIA</th>
					<th class="">BIOPAGO</th>
					
				</tr>
				<tr >
					<td class="">{{($cierre->debito)}}</td>
					<td class="">{{($cierre->efectivo)}}</td>
					<td class="">{{($cierre->transferencia)}}</td>
					<td class="">{{($cierre->caja_biopago)}}</td>
					
					
				</tr>
				
				<tr class="text-success">
					<th colspan="2">
						<h3>FACTURADO REAL</h3>
					</th>
					<th colspan="2">
						<h1 class="">{{($cierre_tot)}}</h1>
					</th>
					
				</tr>
				<tr>
					<th colspan="5">
						<h2>
							NOTA
						</h2>
						{{ ($cierre->nota) }}
					</th>
				</tr>
				<tr>
					<th>
						CRÉDITOS DEL DÍA
					</th>
					<td>
						{{($facturado[4])}}
					</td>
					<th>
						CRÉDITO POR COBRAR TOTAL
					</th>
					<td><span class="h2">{{number_format($cred_total,2)}}</span></td>
				</tr>
				<tr>
					<td colspan="5"><b>ABONOS DEL DÍA</b> <br> <span class="h2">{{number_format($abonosdeldia,2)}}</span></td>
				</tr>
				
				@foreach ($pedidos_abonos as $e)
					<tr>
						<td>
							Abono. #{{$e->id}}
						</td>
						<td colspan="3">
							Cliente: {{$e->cliente->nombre}}. {{$e->cliente->identificacion}}
						</td>
						<td>
							@foreach ($e->pagos as $ee)
								
								<span> 
									<b>
										@switch($ee->tipo)
											@case(1)
											Transferencia
											@break
											@case(2)
											Debito
											@break
											
											@case(3)
											Efectivo
											@break
											@case(4)
											Credito
											@break
											@case(5)
											Otros
											@break
											@case(6)
											Vuelto
											@break
										@endswitch  
									</b>
									{{$ee->monto}}
								</span>
								<br>
							@endforeach
						</td>
					</tr>
				@endforeach

				<tr>
					<th colspan="5">REFERENCIAS DE PAGOS ELECTRÓNICOS</th>

				</tr>
				<tr>
					<th>BANCO</th>
					<th>CONCEPTO</th>
					<th>MONTO</th>
					<th></th>
					<th>HORA</th>
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
						<td>{{$e->monto}}</td>
						<td>Pedido #{{$e->id_pedido}}</td>
						<th>{{$e->created_at}}</th>
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

				@foreach ($facturado["puntosAdicional"] as $e)
					<tr>
						<td>{{$e["banco"]}}</td>
						<th>{{$e["descripcion"]}} ({{$e["categoria"]}})</th>
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
									<table class="text-left">
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
									</table>
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