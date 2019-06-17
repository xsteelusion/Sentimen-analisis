<?php 
	require_once "koneksi.php";
	require_once "vendor/autoload.php";
	require_once "kakas/IndonesianSentenceFormalizer.php";
	
	use Phpml\Dataset\ArrayDataset;
	use Phpml\Classification\KNearestNeighbors;

	set_time_limit(60);

	$stmt = $conn->prepare("SELECT id_data_testing, tweet, kelas_aktual FROM data_testing");

	$stmt->execute();
	while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
		$data_testing[] = $row;
	}

	//TAHAPAN PREPROCESSING

		$hasil_preprocessing = array();

		foreach ($data_testing as $value) {
			extract($value);
	        $hasil_preprocessing[$id_data_testing]['id_data_testing'] = $id_data_testing;
	        $hasil_preprocessing[$id_data_testing]['tweet'] = $tweet;
	        $hasil_preprocessing[$id_data_testing]['kelas_aktual'] = $kelas_aktual;
	        
	        $formalizer = new IndonesianSentenceFormalizer(); 
	        $hasil_formalisasi = $formalizer->normalizeSentence($tweet);
		    $hasil_preprocessing[$id_data_testing]['formalisasi'] = $hasil_formalisasi;
		    

	        $stopwordFactory = new \Sastrawi\StopwordRemover\StopwordRemoverFactory();
		    $stopword  = $stopwordFactory->createStopWordRemover();
		    $hasil_stopword_removal =  $stopword->remove($hasil_formalisasi); 
			$hasil_preprocessing[$id_data_testing]['stopword_removal'] = $hasil_stopword_removal;
		  	
		  	$stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
		    $stemmer  = $stemmerFactory->createStemmer();
		    $hasil_stemming = $stemmer->stem($hasil_stopword_removal);
	  		$hasil_preprocessing[$id_data_testing]['stemming'] = $hasil_stemming;
		}

	//TAHAPAN PEMBOBOTAN TF-IDF
		$stmt = $conn->prepare("(SELECT id_data_training AS id_tweet, hasil_preprocessing FROM data_training)
								UNION 
								(SELECT 
									(SELECT MAX(id_data_training) FROM data_training)+id_data_testing AS id_tweet, hasil_preprocessing FROM data_testing)
								ORDER BY id_tweet");

		$stmt->execute();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
			$id_tweet = $row['id_tweet'];
			$dataTweet[$id_tweet] = $row['hasil_preprocessing'];
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
			$dataTrainingSamples[$id_tweet] = $row['hasil_preprocessing'];
			$dataTrainingLabels[$id_tweet] = $row['kelas'];
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


		$dataTraining = new ArrayDataset($trainingVector, $dataTrainingLabels);

		$classifier = new KNearestNeighbors($k = 5);

		$classifier->train($trainingVector, $dataTrainingLabels);



		$bobot = array();		
		foreach ($hasil_preprocessing as $key => $value) {
			//VEKTOR TWEET TESTING

			$id_data_testing = $value['id_data_testing'];
			$hasil_stemming = $value['stemming'];

			$words = explode(" ", $hasil_stemming);
			
			$testingVector = array_fill(0, count($bagOfWords), 0);
			$termFreq[$id_data_testing] = array_fill(0, count($bagOfWords), 0);

			foreach ($words as $word) {	
				if (in_array($word, $bagOfWords)) {
					$key = array_search($word, $bagOfWords);
					$count = array_count_values($words);
					$testingVector[$key] = $count[$word];

					$termFreq[$id_data_testing][$key] = $count[$word];	
				}
			}


	       	//MENGHITUNG TF-IDF DATA TESTING
	        $tf_idf = array();
	        
	        foreach ($testingVector as $index => $tf) {
	            $tf_idf[$index] = $tf * $idf[$index];
	        }

	        $testingVector = $tf_idf;

	        $bobot[$id_data_testing] = $testingVector;	
		}

		$hasil_bobot = array();
		foreach ($bobot as $id_data_testing => $vektor) {
			foreach ($vektor as $id_vektor => $bobot_term) {
				if($bobot_term > 0){
					$hasil_bobot[$id_data_testing][$id_vektor]['tf'] = $termFreq[$id_data_testing][$id_vektor];
					$hasil_bobot[$id_data_testing][$id_vektor]['df'] = $df[$id_vektor];
					$hasil_bobot[$id_data_testing][$id_vektor]['idf'] = $idf[$id_vektor];
					$hasil_bobot[$id_data_testing][$id_vektor]['bobot_term'] = $bobot_term;
				}
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
						<div class="col-lg-12 col-md-12">
							<div class="card card-nav-tabs">
								<div class="card-header" data-background-color="green">
									<div class="nav-tabs-navigation">
										<div class="nav-tabs-wrapper">
											<ul class="nav nav-tabs" data-tabs="tabs">
												<li class="active">
													<a href="#preprocessing" data-toggle="tab">
														PREPROCESSING
													<div class="ripple-container"></div></a>
												</li>
												<li class="">
													<a href="#pembobotan" data-toggle="tab">
														PEMBOBOTAN
													<div class="ripple-container"></div></a>
												</li>
											</ul>
										</div>
									</div>
								</div>

								<div class="card-content table-responsive">
									<div class="tab-content">
										<div class="tab-pane active" id="preprocessing">
											<table id="data_training_positif" class="table table-hover">
												<thead class="text-success">
													<tr>
														<th>ID Tweet</th>
														<th>Tweet</th>
														<th>Hasil Formalisasi</th>
														<th>Hasil Stopword</th>
														<th>Hasil Stemming</th>
													</tr>
												</thead>
												<tbody>
													<?php
														if (isset($hasil_preprocessing)) {
															foreach ($hasil_preprocessing as $key => $value) {
																extract($value);
																echo '<tr>
																	<td>'.$id_data_testing.'</td>
																	<td>'.$tweet.'</td>
																	<td>'.$formalisasi.'</td>
																	<td>'.$stopword_removal.'</td>
																	<td>'.$stemming.'</td>
																</tr>';		
															}
													 	} 
													?>
												</tbody>
											</table>
										</div>
										<div class="tab-pane" id="pembobotan">
											<table id="data_training_negatif" class="table table-hover">
												<thead class="text-success">
													<tr>
														<th>ID Tweet</th>
														<th>Keterangan</th>
														<th colspan="25">Pembobotan</th>
													</tr>
												</thead>
												<tbody>
													<?php
														if (isset($hasil_bobot)) {
															foreach ($hasil_bobot as $id_data_testing => $bobot) {
																echo '<tr>';
																	echo '<td rowspan="5">'.$id_data_testing.'</td>';
																	echo '<td><strong>Term</strong></td>';
																	foreach ($bagOfWords as $id_term => $term) {
																		if(array_key_exists($id_term, $bobot)){
																			echo '<td><strong>'.$term.'</strong></td>';
																		}
																	}
																echo '</tr>';
																echo '<tr>';
																	echo '<td></strong>tf</strong></td>';
																	foreach ($bagOfWords as $id_term => $value) {
																		if(array_key_exists($id_term, $bobot)){
																			echo '<td>'.$bobot[$id_term]['tf'].'</td>';
																		}
																	}
																echo '</tr>';
																echo '<tr>';
																	echo '<td><strong>df</strong></td>';
																	foreach ($bagOfWords as $id_term => $value) {
																		if(array_key_exists($id_term, $bobot)){
																			echo '<td>'.$bobot[$id_term]['df'].'</td>';
																		}
																	}
																echo '</tr>';
																echo '<tr>';
																	echo '<td><strong>idf</strong></td>';
																	foreach ($bagOfWords as $id_term => $value) {
																		if(array_key_exists($id_term, $bobot)){
																			echo '<td>'.round($bobot[$id_term]['idf'], 3).'</td>';
																		}
																	}
																echo '</tr>';
																echo '<tr>';
																	echo '<td><strong>Bobot</strong></td>';
																	foreach ($bagOfWords as $id_term => $value) {
																		if(array_key_exists($id_term, $bobot)){
																			echo '<td>'.round($bobot[$id_term]['bobot_term'],3).'</td>';
																		}
																	}

																echo '</tr>';
																unset($bobot[$id_term]);
															}	
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

	<!-- Modal Core -->
	<div class="modal fade" id="hapusModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="false">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	        <h4 class="modal-title" id="myModalLabel">Hapus Data</h4>
	      </div>
	      <div class="modal-body">
	      	Apakah anda yakin akan menghapus data ini ?
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default btn-simple" data-dismiss="modal">Tidak</button>
	        <button type="button" class="btn btn-info btn-simple"><a href="proses_reset_data.php">Ya</a></button>
	      </div>
	    </div>
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

	<script type="text/javascript">
    	$(document).ready(function(){


    	});
	</script>
</html>
