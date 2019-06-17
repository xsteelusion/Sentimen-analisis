<?php
  require_once "koneksi.php";
  require_once "CsvImport.php";
  // require_once "kakas/IndonesianSentenceFormalizer.php";
  require_once "vendor/autoload.php";

  if(isset($_POST['upload_data_training'])) {
    $target = NULL;
    if(isset($_FILES['data_training']) && is_uploaded_file($_FILES['data_training']['tmp_name'])) //cek jika telah upload file
    {
      $filename  = basename($_FILES['data_training']['name']);
      $extension = pathinfo($filename, PATHINFO_EXTENSION);
      $source = $_FILES['data_training']['tmp_name'];

      if($extension === 'csv') //format file yang diperbolehkan
      {
        $dir = 'data';

        $target = $dir.'/'.$filename;
        move_uploaded_file($source, $target);

        set_time_limit(120);

        $data_tweet = array(0,1,2);

        $header = true;

        $data = CsvImport::loadCsv(__DIR__ . DIRECTORY_SEPARATOR . $target, $data_tweet, $header);

        // $formalizer = new IndonesianSentenceFormalizer();

        try{

            $stmt = $conn->prepare("INSERT INTO data_training VALUES(:id_tweet, :tweet, :hasil_preprocessing, :kelas)");
            $conn->beginTransaction();

            foreach ($data as $value) {

              extract($value);

              // $hasil_formalisasi = $formalizer->normalizeSentence($tweet[1]);

              $stopwordFactory = new \Sastrawi\StopwordRemover\StopwordRemoverFactory();
              $stopword  = $stopwordFactory->createStopWordRemover();
              $hasil_stopword_removal =  $stopword->remove($tweet[1]);

              $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
              $stemmer  = $stemmerFactory->createStemmer();
              $hasil_stemming = $stemmer->stem($hasil_stopword_removal);

              $stmt->bindValue(':id_tweet', $tweet[0]);
              $stmt->bindParam(':tweet', $tweet[1]);
              $stmt->bindParam(':hasil_preprocessing', $hasil_stemming);
              $stmt->bindParam(':kelas', $tweet[2]);

              $stmt->execute();
            }

            $conn->commit();

            $msg = "Berhasil Input Data";

        }catch(PDOException $e){
            echo $e->getMessage();

        }

      }
      else
      {
        $msg = "Format file tidak diijinkan";
      }
    }
  }
  else if(isset($_POST['upload_data_testing'])) {
    $target = NULL;
    if(isset($_FILES['data_testing']) && is_uploaded_file($_FILES['data_testing']['tmp_name'])) //cek jika telah upload file
    {
      $filename  = basename($_FILES['data_testing']['name']);
      $extension = pathinfo($filename, PATHINFO_EXTENSION);
      $source = $_FILES['data_testing']['tmp_name'];

      if($extension === 'csv') //format file yang diperbolehkan
      {
        $dir = 'data';

        $target = $dir.'/'.$filename;
        move_uploaded_file($source, $target);

        set_time_limit(120);

        $data_tweet = array(0,1,2);

        $header = true;

        $data = CsvImport::loadCsv(__DIR__ . DIRECTORY_SEPARATOR . $target, $data_tweet, $header);

        // $formalizer = new IndonesianSentenceFormalizer();

        try{

            $stmt = $conn->prepare("INSERT INTO data_testing VALUES(:id_tweet, :tweet, :hasil_preprocessing, :kelas_aktual, :kelas_prediksi)");
            $conn->beginTransaction();

            foreach ($data as $value) {

              extract($value);

              // $hasil_formalisasi = $formalizer->normalizeSentence($tweet[1]);

              $stopwordFactory = new \Sastrawi\StopwordRemover\StopwordRemoverFactory();
              $stopword  = $stopwordFactory->createStopWordRemover();
              $hasil_stopword_removal =  $stopword->remove($tweet[1]);

              $stemmerFactory = new \Sastrawi\Stemmer\StemmerFactory();
              $stemmer  = $stemmerFactory->createStemmer();
              $hasil_stemming = $stemmer->stem($hasil_stopword_removal);

              $stmt->bindValue(':id_tweet', $tweet[0]);
              $stmt->bindParam(':tweet', $tweet[1]);
              $stmt->bindParam(':hasil_preprocessing', $hasil_stemming);
              $stmt->bindParam(':kelas_aktual', $tweet[2]);
              $stmt->bindValue(':kelas_prediksi', NULL);

              $stmt->execute();
            }

            $conn->commit();

            $msg = "Berhasil Input Data";

        }catch(PDOException $e){
            echo $e->getMessage();

        }

      }
      else
      {
        $msg = "Format file tidak diijinkan";
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
                <div class="ripple-container"></div>
                </a>
              </li>
              <li class="active" class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                  <i class="material-icons">assignment</i>Data
                  <b class="caret"></b>
                  <div class="ripple-container"></div>
                </a>
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
						<div class="col-lg-7 col-md-12">
							<div class="card card-nav-tabs">
	              <div class="card-header" data-background-color="blue">
									<div class="nav-tabs-navigation">
										<div class="nav-tabs-wrapper">
											<span class="nav-tabs-title">INPUT DATA</span>
											<ul class="nav nav-tabs" data-tabs="tabs">
												<li class="active">
													<a href="#input-data-training" data-toggle="tab">
														INPUT DATA TRAINING
													<div class="ripple-container"></div></a>
												</li>
												<li>
													<a href="#input-data-testing" data-toggle="tab">
														INPUT DATA TESTING
													<div class="ripple-container"></div></a>
												</li>
											</ul>
										</div>
									</div>
								</div>
                  <div class="card-content table-responsive">
                    <div class="tab-content">
										  <div class="tab-pane active" id="input-data-training">
											   <?php
  					                if(isset($msg)){
  					                  echo '
  					                  <div class="alert alert-primary">
      													<div class="container-fluid">
      													  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      														<span aria-hidden="true"><i class="material-icons">clear</i></span>
      													  </button>
      													  '.strtoupper($msg).'
      													</div>
      												</div>';
      								      }
							           ?>
											  <p>Inputkan file dalam format (*.csv)</p>
                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] ?>" enctype="multipart/form-data">
                          <div class="col-sm-4">
                            <input type="file" name="data_training">
                            <input type="submit" name="upload_data_training" class="btn btn-info" value="Simpan">
                          </div>
                        </form>
										</div>
										<div class="tab-pane" id="input-data-testing">
											<p>Inputkan file dalam format (*.csv)</p>
											<form method="POST" action="<?php echo $_SERVER['PHP_SELF'] ?>" enctype="multipart/form-data">
												<div class="col-sm-4">
													<input type="file" name="data_testing">
													<input type="submit" name="upload_data_testing" class="btn btn-info" value="Simpan">
												</div>
											</form>
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

			// Javascript method's body can be found in assets/js/demos.js
        	demo.initDashboardPageCharts();

    	});
	</script>
</html>
