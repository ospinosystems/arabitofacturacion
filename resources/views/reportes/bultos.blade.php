
@extends('layouts.app')
@section('tittle'," Mucho más que una ferreteria")
@section('content')
@foreach ($bultos as $num_bulto => $e)
    
    <div class= "" id = "divEtiqueta">
        <div class="text-center">
            <div class="text-center">
                <label class= "sucursal">
                    <b class="fs-1">{{$sucursal}}</b>
                </label>
            </div>
            <label class="numbultos fs-1">
               <b> {{$num_bulto}} / {{$total}}</b>
            </label>
            <br>
            <br>
            {{-- <label class="numped">
                <b class="fs-6">ITEMS {{count($e)}}</b>
            </label>
            <br> --}}
        </div>
        @if(isset($id_pedido_central) && $id_pedido_central !== null && $id_pedido_central !== '')
        <div class="text-center w-100">
            <label class="num-pedido-central d-block w-100">
                <b class="muted">#{{ $id_pedido_central }}</b>
            </label>
        </div>
        @endif
        <label class="fecha d-block w-100">
            ORIGEN: <b class="muted">{{ $origen }}</b>
        </label>
        @if(isset($codigo_destino) && $codigo_destino !== null && trim((string) $codigo_destino) !== '')
        <label class="fecha d-block w-100">
            DESTINO: <b class="muted">{{ strtoupper(trim((string) $codigo_destino)) }}</b>
        </label>
        @endif
        <label class="fecha d-block w-100">
            <b class="muted">{{ $fecha }}</b>
        </label>
        


    </div>
    <div class="pagebreak"> </div>

@endforeach





    <style>
        #divEtiqueta{
            width: 57mm;
            height: 44mm;
            padding: 3px;
            overflow: hidden;
            font-family: arial;
        }
        .numbultos{
            width: 100%;
            font-size: 0.5rem;
        }
        .numped{
            width: 100%;
            font-size: 0.5rem;
        }
        .fecha{
            width: 100%;
            font-size: 0.6rem;
        }
        
        .sucursal{
            font-size: 0.7rem;
            font-weight: bold;
            text-align: center
        }
        .num-pedido-central{
            font-size: 0.78rem;
            font-weight: bold;
            text-align: center;
            margin-top: 2px;
            margin-bottom: 2px;
            line-height: 1.1;
        }
        
        @media print {
            .pagebreak { page-break-before: always; } /* page-break-after works, as well */
        }


    </style>

    <script>

    setTimeout(() => {

    window.print();  

    }, 2000);
   




    setTimeout(() => {

    window.close();

    }, 3000);

    window.onfocus = function () { setTimeout(function () { window.close(); }, 3000); }
   
    </script>
@endsection




