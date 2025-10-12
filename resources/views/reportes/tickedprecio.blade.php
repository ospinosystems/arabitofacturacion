
@extends('layouts.app')
@section('tittle'," Mucho más que una ferreteria")
@section('content')

<div class= "" id = "divEtiqueta">
    
    
    
    <label class="titulo">
        CODIGO:
    </label>
    <label>
        {{$codigo_barras}}
    </label>
    <br>
    <label class="titulo" hidden>
        DESCRIPCION:
    </label>
    <label class= "descripcion">
        {{substr($descripcion, 0, 56)}}
    
    </label>

    <label class="titulo">
        REF:
    </label>
    <label class ="precio">
            {{$pu}}
        
        </label>

    </div>





    <style>
        #divEtiqueta{
            width: 57mm;
            height: 44mm;
            padding: 3px;
            overflow: hidden;
            font-family: arial;
      
        }
        .titulo{
            width: 100%;
            font-size: 0.5rem;
            margin-bottom: -4px;
        }
        .descripcion{
            font-size: 0.7rem;
            font-weight: bold;
        }

        .precio{
            font-size: 2rem;
            font-weight: bold;
            width: 90%;
            text-align: center;
            margin-top: -10px;
            letter-spacing: 0.3rem;
            

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




