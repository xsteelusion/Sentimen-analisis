<?php  
    require_once "koneksi.php";

    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

    $sql = "
    SET FOREIGN_KEY_CHECKS = 0; 
    
    TRUNCATE data_training;
    ALTER TABLE data_training AUTO_INCREMENT = 1;
    
    TRUNCATE data_testing;
    ALTER TABLE data_testing AUTO_INCREMENT = 1;

    SET FOREIGN_KEY_CHECKS = 1;
    ";

    try {
        $conn->exec($sql);
        $msg = "Berhasil Reset Data Training";
        header("location:tampil_data.php");
    }
    catch (PDOException $e)
    {
        echo $e->getMessage();
        die();
    }

?>

