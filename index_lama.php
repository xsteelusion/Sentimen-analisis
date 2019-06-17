<?php
	require_once "koneksi.php";
	require_once "vendor/autoload.php";
	// require_once "kakas/IndonesianSentenceFormalizer.php";

	use Phpml\Dataset\ArrayDataset;
	use Phpml\Classification\KNearestNeighbors;


	//DATA TRAINING
    $stmt = $conn->prepare("SELECT COUNT(*) AS juml FROM data_training");
    $stmt->execute();
    $juml_training = $stmt->fetch(PDO::FETCH_NUM);
    $juml_training = $juml_training[0];

    //DATA TRAINING POSITIF
    $stmt = $conn->prepare("SELECT COUNT(*) FROM data_training WHERE kelas = 'positif'");
    $stmt->execute();
    $juml_training_positif = $stmt->fetch(PDO::FETCH_NUM);
    $juml_training_positif = $juml_training_positif[0];

	//DATA TRAINING NEGATIF
    $stmt = $conn->prepare("SELECT COUNT(*) FROM data_training WHERE kelas = 'negatif'");
    $stmt->execute();
    $juml_training_negatif = $stmt->fetch(PDO::FETCH_NUM);
    $juml_training_negatif = $juml_training_negatif[0];


	//DATA TESTING
    $stmt = $conn->prepare("SELECT COUNT(*) AS juml FROM data_testing");
    $stmt->execute();
    $juml_testing = $stmt->fetch(PDO::FETCH_NUM);
    $juml_testing = $juml_testing[0];

	//DATA TESTING POSITIF
    $stmt = $conn->prepare("SELECT COUNT(*) FROM data_testing WHERE kelas_aktual = 'positif'");
    $stmt->execute();
    $juml_testing_positif = $stmt->fetch(PDO::FETCH_NUM);
    $juml_testing_positif = $juml_testing_positif[0];

    //DATA TESTING NEGATIF
    $stmt = $conn->prepare("SELECT COUNT(*) FROM data_testing WHERE kelas_aktual = 'negatif'");
    $stmt->execute();
    $juml_testing_negatif = $stmt->fetch(PDO::FETCH_NUM);
    $juml_testing_negatif = $juml_testing_negatif[0];


	//JUMLAH DATA YANG DIKLASIFIKASI DENGAN BENAR
    $stmt = $conn->prepare("SELECT COUNT(*) AS juml_benar FROM data_testing WHERE kelas_aktual = kelas_prediksi");
    $stmt->execute();
    $juml_benar = $stmt->fetch(PDO::FETCH_NUM);
    $juml_benar = $juml_benar[0];

    //MENGHITUNG AKURASI
    if($juml_testing){
    	$akurasi = round(($juml_benar / $juml_testing) * 100, 2);
    }else{
    	$akurasi = 0;
    }




	if(isset($_POST['klasifikasi'])){
		extract($_POST);

		$tweet_testing = $_POST['tweet_testing'];


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

		$bagOfWords = array();
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


		// $formalizer = new IndonesianSentenceFormalizer();

        // $hasil_formalisasi = $formalizer->normalizeSentence($tweet_testing);

	    $stopwordFactory = new \Sastrawi\StopwordRemover\StopwordRemoverFactory();
	    $stopword  = $stopwordFactory->createStopWordRemover();
	    $hasil_stopword_removal =  $stopword->remove($tweet_testing);

	    $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
	    $stemmer  = $stemmerFactory->createStemmer();
	    $hasil_stemming = $stemmer->stem($hasil_stopword_removal);


		//VEKTOR TWEET TESTING
		$words = explode(" ", $hasil_stemming);
		$testingVector = array_fill(0, count($bagOfWords), 0);
		foreach ($words as $word) {
			if (in_array($word, $bagOfWords)) {
				$key = array_search($word, $bagOfWords);
				$count = array_count_values($words);
				$testingVector[$key] = $count[$word];
			}
		}


		if(isset($dataTrainingSamples)){

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

	        foreach ($testingVector as $index => $tf) {
	            $tf_idf[$index] = $tf * $idf[$index];
	        }

	        $testingVector = $tf_idf;
	        unset($tf_idf);

			$dataTraining = new ArrayDataset($trainingVector, $dataTrainingLabels);

			$classifier = new KNearestNeighbors($k);

			$classifier->train($trainingVector, $dataTrainingLabels);

			$predictedLabels = $classifier->predict($testingVector);

		}
		else{
			$err_msg = "Data training dan data testing tidak tersedia";
		}
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

			<div class="content">
				<div class="container-fluid">
					<div class="row">
						<div class="col-lg-4 col-md-6 col-sm-6">
							<div class="card card-stats">
								<div class="card-header" data-background-color="purple">
									<i class="material-icons">content_paste</i>
								</div>
								<div class="card-content">
									<p class="category">Data Training</p>
									<?php
									if(isset($juml_training)){
										echo '<h3 class="title">'.$juml_training.'&nbsp;<small>data</small></h3>';
									}else{
										echo '<h3 class="title">- &nbsp;<small>data</small></h3>';
									} ?>

								</div>
								<div class="card-footer">
									<table class="table">
										<tbody>
											<tr>
												<td class="td-actions">
													<i class="material-icons">add</i>
												</td>
												<?php
													if(isset($juml_training_positif)){
														echo '<td>'.$juml_training_positif.' <small>data</small></td>';
													}else{
														echo '<td></td>';
													}
												?>
											</tr>
											<tr>
												<td class="td-actions">
													<i class="material-icons">remove</i>
												</td>
												<?php
													if(isset($juml_training_negatif)){
														echo '<td>'.$juml_training_negatif.' <small>data</small></td>';
													}else{
														echo '<td></td>';
													}
												?>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<div class="col-lg-4 col-md-6 col-sm-6">
							<div class="card card-stats">
								<div class="card-header" data-background-color="green">
									<i class="material-icons">find_in_page</i>
								</div>
								<div class="card-content">
									<p class="category">Data Testing</p>
									<?php
									if(isset($juml_testing)){
										echo '<h3 class="title">'.$juml_testing.'&nbsp;<small>data</small></h3>';
									}else{
										echo '<h3 class="title">- &nbsp;<small>data</small></h3>';
									} ?>
								</div>
								<div class="card-footer">
									<table class="table">
										<tbody>
											<tr>
												<td class="td-actions">
													<i class="material-icons">add</i>
												</td>
												<?php
													if(isset($juml_testing_positif)){
														echo '<td>'.$juml_testing_positif.' <small>data</small></td>';
													}else{
														echo '<td></td>';
													}
												?>
											</tr>
											<tr>
												<td class="td-actions">
													<i class="material-icons">remove</i>
												</td>
												<?php
													if(isset($juml_testing_negatif)){
														echo '<td>'.$juml_testing_negatif.' <small>data</small></td>';
													}else{
														echo '<td></td>';
													}
												?>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<div class="col-lg-4 col-md-6 col-sm-6">
							<div class="card card-stats">
								<div class="card-header" data-background-color="red">
									<i class="material-icons">check_box</i>
								</div>
								<div class="card-content">
									<p class="category">Akurasi</p>
									<?php
									if(isset($akurasi)){
										echo "<h3 class='title'>".$akurasi."%</h3>";
									}else{
										echo '<h3 class="title"></h3>';
									} ?>

								</div>
								<div class="card-footer">
									<table class="table ">
										<tbody>
											<tr>
												<td>Data yang diklasifikasi dengan benar : </td>
												<?php
												if(isset($juml_testing) && isset($juml_benar)){
													echo '<td>'.$juml_benar.'</td>';
												}else{
													echo '<td></td>';
												} ?>
											</tr>
											<tr>
												<td>Jumlah seluruh data testing : </td>
												<?php
												if(isset($juml_testing)){
													echo '<td>'.$juml_testing.'</td>';
												}else{
													echo '<td></td>';
												} ?>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-12 col-md-12">
							<div class="card">
	                            <div class="card-header" data-background-color="orange">
	                                <h4 class="title">Klasifikasi</h4>
	                                <p class="category">Inputkan teks untuk diklasifikasikan</p>
	                            </div>
	                            <div class="card-content">
	                                <?php
						                if(isset($predictedLabels)){
						                  echo '
						                  <div class="alert alert-info">
											<div class="container-fluid">
											  <div class="alert-icon">
											    <i class="material-icons">label</i>
											  </div>
											  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
												<span aria-hidden="true"><i class="material-icons">clear</i></span>
											  </button>
											  '.strtoupper($predictedLabels).'
											</div>
										  </div>';
						                }
					              	?>
	                                <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] ?>">
										<div class="row">
											<div class="col-xs-offset-1">
												<div class="col-md-6 input-group">
													<textarea id="tweet" class="form-control" name="tweet_testing" placeholder="Inputkan text" rows="3"></textarea>
												</div>
												<div class="col-md-3 input-group">
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
												<div class="col-md-10 input-group">
													<input type="submit" name="klasifikasi" class="btn btn-warning" value="Klasifikasikan">
												</div>
											</div>
										</div>

									</form>
	                            </div>
	                        </div>
						</div>
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

	<!--  Charts Plugin -->
	<script src="assets/js/chartist.min.js"></script>

	<!--  Notifications Plugin    -->
	<script src="assets/js/bootstrap-notify.js"></script>

	<!--  Google Maps Plugin    -->
	<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js"></script>

	<!-- Material Dashboard javascript methods -->
	<script src="assets/js/material-dashboard.js"></script>

	<!-- Material Dashboard DEMO methods, don't include it in your project! -->
	<script src="assets/js/demo.js"></script>


	<script type="text/javascript" src="assets/js/plotly-latest.min.js"></script>
</html>
