<?php
	require_once "koneksi.php";

?>


<!-- klasifikasi -->


<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta name="author" content="Sumon Rahman">
    <meta name="description" content="">
    <meta name="keywords" content="HTML,CSS,XML,JavaScript">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title -->
    <title>Sentimen</title>
    <!-- Place favicon.ico in the root directory -->
    <link rel="apple-touch-icon" href="assets2/images/apple-touch-icon.png">
    <link rel="shortcut icon" type="image/ico" href="assets2/images/favicon.ico" />
    <!-- Plugin-CSS -->
    <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/owl.carousel.min.css">
    <link rel="stylesheet" href="assets2/css/linearicons.css">
    <link rel="stylesheet" href="assets2/css/animate.css">
    <link rel="stylesheet" href="assets2/css/magnific-popup.css">
    <!-- Main-Stylesheets -->
    <link rel="stylesheet" href="assets2/css/normalize.css">
    <link rel="stylesheet" href="assets2/style.css">
    <link rel="stylesheet" href="assets2/css/responsive.css">
    <script src="assets2/js/vendor/modernizr-2.8.3.min.js"></script>


<!-- boostrap lama -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />

    <!--  Material Dashboard CSS    -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet"/>

    <!--  CSS for Demo Purpose, don't include it in your project     -->
    <link href="assets/css/demo.css" rel="stylesheet" />

    <!--     Fonts and icons     -->
    <link href="http://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
    <link href='http://fonts.googleapis.com/css?family=Roboto:400,700,300|Material+Icons' rel='stylesheet' type='text/css'>
    <!--[if lt IE 9]>
        <script src="//oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="//oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body data-spy="scroll" data-target=".mainmenu-area">
    <!-- Preloader-content -->
    <div class="preloader">
        <span><i class="lnr lnr-sun"></i></span>
    </div>
    <!-- MainMenu-Area -->
    <nav class="mainmenu-area" data-spy="affix">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#primary_menu">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#"><img src="assets2/images/logo.png" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="primary_menu">
                <ul class="nav navbar-nav mainmenu">
                    <li><a href="index.php">Dashboard</a></li>
                    <li ><a href="klasifikasi_new.php">Klasifikasi</a></li>
										<li class="dropdown">
											<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
													Data
											<b class="caret"></b>
											<div class="ripple-container"></div></a>
												<ul class="dropdown-menu dropdown-menu-right" style="background-color: #5a8fd6;">
														<li  ><a href="tampil_data_new.php">Tampil Data</a></li>
														<li ><a href="input_data_new.php">Input Data</a></li>
														<li class="active"><a href="reset_data_new.php">Reset Data</a></li>
												</ul>
										</li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- MainMenu-Area-End -->
    <!-- Home-Area -->

    <!-- Download-Area-End -->
    <!--Price-Area -->

	<section class="section-padding price-area">
    <div class="container">
        <div class="row">
						<div class="col-xs-12 col-sm-12 ">
              <div class="price-box">
                <div class="price-header">
                    <h4 class="upper"><button class="btn btn-danger" data-toggle="modal" data-target="#myModal">
																				  Reset Data
																				</button>
										</h4>
                </div>
              </div>
						</div>


			</div>
		</div>
	</section>

	<!-- Modal Core -->
	<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="false">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="myModalLabel">Reset Data</h4>
	      </div>
	      <div class="modal-body">
	      	Apakah anda yakin akan menghapus seluruh data ?
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default btn-simple" data-dismiss="modal">Tidak</button>
	        <button type="button" class="btn btn-info btn-simple"><a href="proses_reset_data.php">Ya</a></button>
	      </div>
	    </div>
	  </div>
	</div>

    <!-- Subscribe-Form-Area -->
    <!-- Footer-Area -->
    <footer class="footer-area" id="contact_page">        <!-- Footer-Bootom -->
        <div class="footer-bottom">
            <div class="container">
                <div class="row">
                    <div class="col-xs-12 col-md-5">
                        <!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->
            <span>Copyright &copy;<script>document.write(new Date().getFullYear());</script> All rights reserved | This template is made with <i class="lnr lnr-heart" aria-hidden="true"></i> by <a href="https://colorlib.com" target="_blank">Colorlib</a></span>
            <!-- Link back to Colorlib can't be removed. Template is licensed under CC BY 3.0. -->
                        <div class="space-30 hidden visible-xs"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer-Bootom-End -->
    </footer>
    <!-- Footer-Area-End -->
    <!--Vendor-JS-->
    <script src="assets2/js/vendor/jquery-1.12.4.min.js"></script>
    <script src="assets2/js/vendor/jquery-ui.js"></script>
    <script src="assets2/js/vendor/bootstrap.min.js"></script>
    <!--Plugin-JS-->
    <script src="assets2/js/owl.carousel.min.js"></script>
    <script src="assets2/js/contact-form.js"></script>
    <script src="assets2/js/ajaxchimp.js"></script>
    <script src="assets2/js/scrollUp.min.js"></script>
    <script src="assets2/js/magnific-popup.min.js"></script>
    <script src="assets2/js/wow.min.js"></script>
    <!--Main-active-JS-->
    <script src="assets2/js/main.js"></script>
</body>

</html>
