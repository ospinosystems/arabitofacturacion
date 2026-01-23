<!DOCTYPE html>
<html lang="en">
<head>
	<meta name="csrf-token" content="{{ csrf_token() }}">

  <link rel="icon" type="image/png" href="{{ asset('images/icon.ico') }}">
  <title>sinapsis</title>

  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <link href="{{ asset('css/table/table.css') }}" rel="stylesheet">

</head>
<body>
	<div className="d-flex justify-content-center flex-wrap align-items-center">
    <div className="p-3">
      <img src="{{asset("images/logo.png")}}" alt="sinapsis" width="200px" className="logo" />
    </div>
  </div>
	<ul className="list-group">
	  <li className="pointer list-group-item d-flex justify-content-between align-items-center">
	    Enviar Fallas
	    <a href="/setCentralData" target="_blank" className="badge bg-sinapsis badge-pill"> 
	    	<i class="fa fa-send"></i> 
	    </a>
	  </li>

	  <li className="pointer list-group-item d-flex justify-content-between align-items-center">
	    Enviar Gastos
	    <a href="/setGastos" target="_blank" className="badge bg-sinapsis badge-pill"> 
	    	<i class="fa fa-send"></i> 
	    </a>
	  </li>

	 
	</ul>
</body>
</html>