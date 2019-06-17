<?php
	require_once "koneksi.php";
	require_once "vendor/autoload.php";

	use Phpml\Dataset\ArrayDataset;
	use Phpml\Classification\KNearestNeighbors;

	session_start();

	$bagOfWords = array();

	set_time_limit(120);

	if(isset($_POST['klasifikasi'])){
		extract($_POST);
		$stmt = $conn->prepare("(SELECT id_data_training AS id_tweet, hasil_preprocessing FROM data_training)
								UNION
								(SELECT
									(SELECT MAX(id_data_training) FROM data_training)+id_data_testing AS id_tweet, hasil_preprocessing FROM data_testing)
								ORDER BY id_tweet");

		$stmt->execute();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			extract($row);
			$dataTweet[$id_tweet] = $hasil_preprocessing;
		}

		//MEMBUAT BAG OF WORDS
		foreach ($dataTweet as $tweet) {
			$words = explode(" ", $tweet);
			foreach ($words as $word) {
				if (!in_array($word, $bagOfWords)) {
					$bagOfWords[] = $word;
				}

			}
		}

		//MEMBUAT VEKTOR YANG BERISI FREKUENSI KATA UNTUK SELURUH DATA
		foreach ($dataTweet as $id_tweet =>  $tweet) {
			$dataVector[$id_tweet] = array_fill(0, count($bagOfWords), 0);
			$words = explode(" ", $tweet);
			foreach ($words as $word) {
				if (in_array($word, $bagOfWords)) {
					$key = array_search($word, $bagOfWords);
					$count = array_count_values($words);
					$dataVector[$id_tweet][$key] = $count[$word];
				}
			}
		}


		//QUERY DATA TRAINING
		$stmt = $conn->prepare("(SELECT id_data_training AS id_tweet, hasil_preprocessing, kelas FROM data_training WHERE kelas = 'positif' ORDER BY id_tweet ASC LIMIT 350)
		UNION
		(SELECT id_data_training AS id_tweet, hasil_preprocessing, kelas FROM data_training WHERE kelas = 'negatif' ORDER BY id_tweet ASC LIMIT 350)
		ORDER by id_tweet");

		$stmt->execute();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			extract($row);
			$dataTrainingSamples[$id_tweet] = $hasil_preprocessing;
			$dataTrainingLabels[$id_tweet] = $kelas;
		}

		if(isset($dataTrainingSamples)){
			$dataTrainingPreprocessing = $dataTrainingSamples;
		}


		//VEKTOR DATA TRAINING
		foreach ($dataTrainingSamples as $id_tweet => $tweet) {
			$words = explode(" ", $tweet);
			$trainingVector[$id_tweet] = array_fill(0, count($bagOfWords), 0);
			foreach ($words as $word) {
				if (in_array($word, $bagOfWords)) {
					$key = array_search($word, $bagOfWords);
					$count = array_count_values($words);
					$trainingVector[$id_tweet][$key] = $count[$word];
				}
			}
		}


		//QUERY DATA TESTING
		$stmt = $conn->prepare("(SELECT id_data_testing AS id_tweet, hasil_preprocessing, kelas_aktual FROM data_testing WHERE kelas_aktual = 'positif' ORDER BY id_tweet ASC LIMIT 150)
		UNION
		(SELECT id_data_testing AS id_tweet, hasil_preprocessing, kelas_aktual FROM data_testing WHERE kelas_aktual = 'negatif' ORDER BY id_tweet ASC LIMIT 150)
		ORDER by id_tweet");

		$stmt->execute();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			extract($row);
			$dataTestingSamples[$id_tweet] = $hasil_preprocessing;
			$dataTestingLabels[$id_tweet] = $kelas;
		}

		if(isset($dataTestingSamples)){
			$dataTestingPreprocessing = $dataTestingSamples;
		}


		//VEKTOR DATA TESTING
		foreach ($dataTestingSamples as $id_tweet => $tweet) {
			$words = explode(" ", $tweet);
			$testingVector[$id_tweet] = array_fill(0, count($bagOfWords), 0);
			foreach ($words as $word) {
				if (in_array($word, $bagOfWords)) {
					$key = array_search($word, $bagOfWords);
					$count = array_count_values($words);
					$testingVector[$id_tweet][$key] = $count[$word];
				}
			}
		}


		//PEMBOBOTAN TF-IDF

		//MENGHITUNG DF (DOCUMENT FREQUENCY)
        $df = array_fill_keys(array_keys(reset($dataVector)), 0);

        foreach ($dataVector as $vector) {
            foreach ($vector as $index => $freq) {
                if ($freq > 0) {
                    $df[$index]++;
                }
            }
        }

        //MENGHITUNG IDF (INVERSE DOCUMENT FREQUENCY)
        $n = count($dataVector);
        $idf = array();
        foreach ($df as $index => $value) {
            if($value == 0){
                $idf[$index] = 0;
            }else{
                $idf[$index] = log((float)($n / $value), 10.0);
            }
        }

        //MENGHITUNG TF-IDF DATA TRAINING
        $tf_idf = array();

        foreach ($trainingVector as $id_tweet => $vector) {
            foreach ($vector as $index => $tf) {
                $tf_idf[$id_tweet][$index] = $tf * $idf[$index];
            }
        }

        $trainingVector = $tf_idf;
        unset($tf_idf);


        //MENGHITUNG TF-IDF DATA TESTING
        $tf_idf = array();
        foreach ($testingVector as $id_tweet => $vector) {
            foreach ($vector as $index => $tf) {
                $tf_idf[$id_tweet][$index] = $tf * $idf[$index];
            }
        }

        $testingVector = $tf_idf;
        unset($tf_idf);


		$dataTraining = new ArrayDataset($trainingVector, $dataTrainingLabels);
		$dataTesting = new ArrayDataset($testingVector, $dataTestingLabels);

		$classifier = new KNearestNeighbors($k);

		$classifier->train($trainingVector, $dataTrainingLabels);

		$predictedLabels = $classifier->predict($testingVector);

		$predictedLabels = array_combine(array_keys($dataTestingLabels), array_values($predictedLabels));


		try{

	      $stmt = $conn->prepare("UPDATE data_testing SET kelas_prediksi = :kelas_prediksi WHERE id_data_testing = :id_data_testing");

	      $conn->beginTransaction();

	      $i = 1;
	      foreach ($dataTestingPreprocessing as $id_tweet => $value) {

		        $stmt->bindParam(":id_data_testing", $id_tweet, PDO::PARAM_INT);
		        $stmt->bindParam(":kelas_prediksi", $predictedLabels[$id_tweet], PDO::PARAM_STR);

		        $stmt->execute();
		        $i++;
	      }
	      $conn->commit();


	    }catch(PDOException $e)
	    {
	      $conn->rollback();
	      echo $e->getMessage();
	    }

    	//DATA TESTING
		$stmt = $conn->prepare("SELECT COUNT(*) AS juml FROM data_testing");
		$stmt->execute();
		$juml_testing = $stmt->fetch(PDO::FETCH_NUM);
		$juml_testing = $juml_testing[0];

		//JUMLAH DATA YANG DIKLASIFIKASI DENGAN BENAR
		$stmt = $conn->prepare("SELECT COUNT(*) AS juml_benar FROM data_testing d WHERE kelas_aktual = kelas_prediksi");
		$stmt->execute();
		$juml_benar = $stmt->fetch(PDO::FETCH_NUM);
		$juml_benar = $juml_benar[0];

		$akurasi = 0;

		//MENGHITUNG AKURASI
		if($juml_testing){
			$akurasi = ($juml_benar / $juml_testing) * 100;
		}

		$_SESSION['k'] = $k;
		$_SESSION['akurasi'] = $akurasi;
	}

	if(isset($_SESSION['k'])){
		$k = $_SESSION['k'];
		$akurasi = $_SESSION['akurasi'];

		unset($_SESSION['k']);
		unset($_SESSION['akurasi']);
	}

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
                    <li  class="active"><a href="klasifikasi_new.php">Klasifikasi</a></li>
										<li class="dropdown ">
											<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
													Data
											<b class="caret"></b>
											<div class="ripple-container"></div></a>
												<ul class="dropdown-menu dropdown-menu-right" style="background-color: #5a8fd6;">
														<li><a href="tampil_data_new.php">Tampil Data</a></li>
														<li><a href="input_data_new.php">Input Data</a></li>
														<li><a href="reset_data_new.php">Reset Data</a></li>
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

							<div class="col-xs-4 col-sm-4">
								<div class="col-xs-12 col-sm-12">
                  <div class="price-box">
                      <div class="price-header">
                          <h4 class="upper">Proses Klasifikasi</h4>
                      </div>
                      <div class="price-body">
                        <div class="card-content">
													<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
														<div class="row">
															<div class="col-md-9 col-md-offset-3">
																<div class="form-group label-floating col-md-6">
																	<label class="control-label">Input Nilai K</label>
														<select name="k" class="form-control">
															<option value="3" selected>3</option>
															<option value="5">5</option>
															<option value="7">7</option>
															<option value="9">9</option>
															<option value="11">11</option>
															<option value="13">13</option>
														</select>
													</div>
											</div>
									</div>
											<div class="col-md-12">
											<input type="submit" name="klasifikasi" class="btn btn-primary" value="Lakukan Klasifikasi">
										</div>
													</form>

                        </div>
											 </div>
                  </div>
                  <div class="space-30 hidden visible-xs"></div>
								</div>

									<?php
										if (isset($akurasi)) {
											echo '
											<div class="col-xs-12 col-sm-12">
												<div class="price-box">
														<div class="price-header">
																<h4 class="upper">HASIL</h4>
														</div>
														<div class="price-body">
															<div class="card-content">
															<div class="card-content table-responsive">';

									echo '<h5 class="text-center">K : '.$k.'</h5>';
									echo '<h5 class="text-center">Akurasi : '.round($akurasi, 2).'</h5>';

															echo '</div>

															</div>
														 </div>
												</div>
												<div class="space-30 hidden visible-xs"></div>
											</div>';
										}
									?>
              </div>









					<?php

					//HASIL KLASIFIKASI
					$stmt = $conn->prepare("SELECT * FROM data_testing");
					$stmt->execute();
					while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
						$data_hasil_klasifikasi[] = $row;
					}

					if (!empty($data_hasil_klasifikasi)) { ?>

						<div class="col-xs-8 col-sm-8">
							<div class="price-box">
									<div class="price-header">
											<h4 class="upper">Hasil Klasifikasi</h4>
									</div>
									<div class="price-body">
										<div class="card-content">
											<div class="card-content table-responsive">
													<div class="tab-content text-left">
														<table id="data_training_positif" class="table table-hover">
															<thead class="text-warning">
																<tr>
																	<th>ID</th>
																	<th>Review</th>
																	<th>Hasil Preprocessing</th>
																	<th>Kelas Aktual</th>
																	<th>Kelas Prediksi</th>
																</tr>
															</thead>
															<tbody>
																<?php
																	foreach ($data_hasil_klasifikasi as $value) {
																		extract($value);
																		echo '<tr>
																			<td>'.$id_data_testing.'</td>
																			<td>'.$tweet.'</td>
																			<td>'.$hasil_preprocessing.'</td>
																			<td>'.$kelas_aktual.'</td>
																			<td>'.$kelas_prediksi.'</td>
																		</tr>';
																	}
																?>

															</tbody>
														</table>
													</div>
											</div>
										</div>
									 </div>
							</div>
						</div>

					<?php } else { ?>
							<div class="col-lg-6">
							<?php
											if(isset($err_msg)){
												echo '
												<div class="alert alert-danger">
								<div class="container-fluid">
									<div class="alert-icon">
										<i class="material-icons">error_outline</i>
									</div>
									<button type="button" class="close" data-dismiss="alert" aria-label="Close">
									<span aria-hidden="true"><i class="material-icons">clear</i></span>
									</button>
									'.$err_msg.'
								</div>
								</div>';
											}
										?>
										</div>
					<?php } ?>
				</div>
			</div>
		</section>



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
