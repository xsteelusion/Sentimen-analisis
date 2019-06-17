<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<link rel="apple-touch-icon" sizes="76x76" href="assets/img/apple-icon.png" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<title>Analisis Sentimen</title>
	<meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
	<meta name="viewport" content="width=device-width" />
	<link href="assets/css/bootstrap.min.css" rel="stylesheet" />
	<link href="assets/css/material-dashboard.css" rel="stylesheet"/>
	<link href="http://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
	<link href='http://fonts.googleapis.com/css?family=Roboto:400,700,300|Material+Icons' rel='stylesheet' type='text/css'>

	<script src="assets/js/jquery-3.1.0.min.js" type="text/javascript"></script>
	<script src="assets/js/bootstrap.min.js" type="text/javascript"></script>
	<script src="assets/js/material.min.js" type="text/javascript"></script>
	<script src="assets/js/chartist.min.js"></script>
	<script src="assets/js/bootstrap-notify.js"></script>
	<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js"></script>
	<script src="assets/js/material-dashboard.js"></script>
	<script src="assets/js/demo.js"></script>


	<script type="text/javascript" src="assets/js/plotly-latest.min.js"></script>
	</html>
</head>

<body>
	<div class="wrapper">
		<div class="main-panel">
			<nav class="navbar navbar-info navbar-absolute">
				<div class="container-fluid">
					<div class="navbar-header">
						<button type="button" class="navbar-toggle" data-toggle="collapse">
							<span class="sr-only">Toggle navigation</span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
						</button>
						<a class="navbar-brand" href="index.php">Analisis Sentimen<div class="ripple-container"></div></a>
					</div>
					<div class="collapse navbar-collapse" id="example-navbar-primary">
						<ul class="nav navbar-nav navbar-right">
							<li class="active">
								<a href="index.php">
									<i class="material-icons">dashboard</i>
									Dashboard
									<div class="ripple-container"></div></a>
								</li>
								<li class="dropdown">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
										<i class="material-icons">assignment</i>Data
										<b class="caret"></b>
										<div class="ripple-container"></div></a>
										<ul class="dropdown-menu dropdown-menu-right">
											<li><a href="tampil_data.php">Tampil Data</a></li>
											<li><a href="input_data.php">Input Data</a></li>
											<li><a href="reset_data.php">Reset Data</a></li>
										</ul>
									</li>
									<li>
										<a href="Klasifikasi.php">
											<i class="material-icons">label</i>
											Klasifikasi
										</a>
									</li>
								</ul>
							</div>
						</div>
					</nav>
