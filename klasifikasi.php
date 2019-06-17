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

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<link rel="apple-touch-icon" sizes="76x76" href="assets/img/apple-icon.png" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

	<title>Analisis Sentimen</title>

	<meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />

    <!-- Bootstrap core CSS     -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" />

    <!--  Material Dashboard CSS    -->
    <link href="assets/css/material-dashboard.css" rel="stylesheet"/>

    <!--  CSS for Demo Purpose, don't include it in your project     -->
    <link href="assets/css/demo.css" rel="stylesheet" />

    <!--     Fonts and icons     -->
    <link href="http://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">
    <link href='http://fonts.googleapis.com/css?family=Roboto:400,700,300|Material+Icons' rel='stylesheet' type='text/css'>

</head>

<body>

	<div class="wrapper">

	    <div class="main-panel">
			<nav class="navbar navbar-info navbar-fixed-top navbar-color-on-scroll">
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
							<li>
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
																<li><a href="input_data_new.php">Input Data</a></li>
																<li><a href="reset_data.php">Reset Data</a></li>
	                            </ul>
	                    	</li>
                            <li class="active">
                                <a href="Klasifikasi.php">
									<i class="material-icons">label</i>
									Klasifikasi
                                </a>
                            </li>
						</ul>
					</div>
				</div>
			</nav>

			<div class="content">
				<div class="container-fluid">
					<div class="row">
						<div class="col-lg-4 col-md-12">
							<div class="container-fluid">
								<div class="row">
									<div class="col-md-12">
										<div class="card card-nav-tabs">
				                            <div class="card-header" data-background-color="purple">
												<div class="nav-tabs-navigation">
													<div class="nav-tabs-wrapper">
														<span class="nav-tabs-title">PROSES KLASIFIKASI</span>
														<ul class="nav nav-tabs" data-tabs="tabs">

														</ul>
													</div>
												</div>
											</div>
				                            <div class="card-content table-responsive">
				                                <div class="text-center">
				                                	<form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
				                                		<div class="row">
				                                			<div class="col-md-9 col-md-offset-3">
						                                		<div class="form-group label-floating col-md-6">
						                                		  <label class="control-label">Input Nilai K</label>
											                      <select name="k" class="form-control">
											                      	<option value="3">3</option>
											                      	<option value="5" selected>5</option>
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
									</div>
									<?php
										if (isset($akurasi)) {
											echo '
											<div class="col-md-12">
												<div class="card card-nav-tabs">
						                            <div class="card-header" data-background-color="blue">
														<div class="nav-tabs-navigation">
															<div class="nav-tabs-wrapper">
																<span class="nav-tabs-title">HASIL</span>
																<ul class="nav nav-tabs" data-tabs="tabs">

																</ul>
															</div>
														</div>
													</div>
						                            <div class="card-content table-responsive">';

														echo '<h5 class="text-center">K : '.$k.'</h5>';
														echo '<h5 class="text-center">Akurasi : '.round($akurasi, 2).'</h5>';

						                            echo '</div>
						                        </div>
											</div>
											';
										}
									?>

								</div>
							</div>
						</div>

						<?php

						//HASIL KLASIFIKASI
						$stmt = $conn->prepare("SELECT * FROM data_testing");
						$stmt->execute();
						while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
							$data_hasil_klasifikasi[] = $row;
						}

						if (!empty($data_hasil_klasifikasi)) { ?>
						<div class="col-lg-8 col-md-12">
							<div class="card card-nav-tabs">
	                            <div class="card-header" data-background-color="green">
									<div class="nav-tabs-navigation">
										<div class="nav-tabs-wrapper">
											<span class="nav-tabs-title">HASIL KLASIFIKASI</span>
											<ul class="nav nav-tabs" data-tabs="tabs">

											</ul>
										</div>
									</div>
								</div>
	                            <div class="card-content table-responsive">
	                                <div class="tab-content">
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
			</div>

			<footer class="footer">
				<div class="container-fluid">
					<p class="copyright pull-right">
						&copy; <script>document.write(new Date().getFullYear())</script> <a href="http://www.creative-tim.com">Creative Tim</a>, made with love for a better web
					</p>
				</div>
			</footer>
		</div>
	</div>

</body>

	<!--   Core JS Files   -->
	<script src="assets/js/jquery-3.1.0.min.js" type="text/javascript"></script>
	<script src="assets/js/bootstrap.min.js" type="text/javascript"></script>
	<script src="assets/js/material.min.js" type="text/javascript"></script>

	<!-- Material Dashboard javascript methods -->
	<script src="assets/js/material-dashboard.js"></script>

	<!-- Material Dashboard DEMO methods, don't include it in your project! -->
	<script src="assets/js/demo.js"></script>

</html>
