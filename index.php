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


<!-- klasifikasi -->


<!doctype html>
<html class="no-js" lang="zxx">

<head>
	<style>
	textarea {
	  width: 100%;
	  height: 150px;
	  padding: 12px 20px;
	  box-sizing: border-box;
	  border: 2px solid #ccc;
	  border-radius: 4px;
	  background-color: #f8f8f8;
	  resize: none;
	}
	</style>
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
                    <li  class="active"><a href="index.php">Dashboard</a></li>
                    <li><a href="klasifikasi_new.php">Klasifikasi</a></li>
                    <li class="dropdown">
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
    <section class="section-padding price-area" id="dashboard">
        <div class="container">
            <div class="row">
              <div class="col-xs-12 col-sm-4">
                  <div class="price-box">
                      <div class="price-header">
                          <div class="price-icon">
                              <i style="font-size: 1.5em;" class="material-icons">content_paste</i>
                          </div>
                          <h4 class="upper">Data Training</h4>
                          <?php
                          if(isset($juml_training)){
                            echo '<h3 class="title">'.$juml_training.'&nbsp;data</h3>';
                          }else{
                            echo '<h3 class="title">- &nbsp;data</h3>';
                          } ?>
                      </div>
                      <div class="price-body">
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
                  <div class="space-30 hidden visible-xs"></div>
              </div>
              <div class="col-xs-12 col-sm-4">
                  <div class="price-box">
                      <div class="price-header">
                          <div class="price-icon">
                              <i style="font-size: 1.5em;" class="material-icons">find_in_page</i>
                          </div>
                          <h4 class="upper">Data Testing</h4>
                          <?php
                          if(isset($juml_testing)){
                            echo '<h3 class="title">'.$juml_testing.'&nbsp;data</h3>';
                          }else{
                            echo '<h3 class="title">- &nbsp;data</h3>';
                          } ?>

                      </div>
                      <div class="price-body">
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
                  <div class="space-30 hidden visible-xs"></div>
              </div>
              <div class="col-xs-12 col-sm-4">
                  <div class="price-box">
                      <div class="price-header">
                          <div class="price-icon">
                              <i style="font-size: 1.5em;" class="material-icons">check_box</i>
                          </div>
                          <h4 class="upper">Akurasi</h4>
                          <?php
                          if(isset($akurasi)){
                            echo "<h3 class='title'>".$akurasi."%</h3>";
                          }else{
                            echo '<h3 class="title"></h3>';
                          } ?>
                      </div>
                      <div class="price-body">
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
                  <div class="space-30 hidden visible-xs"></div>
              </div>
              <div class="col-xs-12 col-sm-12">
                  <div class="price-box">
                      <div class="price-header">
                          <h4 class="upper">Klasifikasi</h4>
                          Inputkan teks untuk diklasifikasikan
                      </div>
                      <div class="price-body">
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
                        </div>                      </div>

                  </div>
                  <div class="space-30 hidden visible-xs"></div>
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
