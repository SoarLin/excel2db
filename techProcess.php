<?php
require_once 'include/config.php';
// require_once 'include/ExcelToMySQL.php';
require_once 'include/ExcelToSQL.php';
include_once 'include/sql/TempDB.php';
ini_set('date.timezone','Asia/Taipei');

define('SITE_ROOT', realpath(dirname(__FILE__)));

$path = 'tech_report/';
$Iterator  = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD);

$i = 1;
$handle_start = microtime(true);
$processd_folder = array();
$stpiArr = array();
foreach($Iterator as $name => $object) {
    if ($object->isFile() && strpos($object->getFilename(), 'xls')) {

        $pathname = $object->getPathname();
        $names = explode('/', $pathname);

        $excel_name = $object->getFilename();
        echo $i++.". ";

        ImportExcel($devDB, $pathname, $excel_name, $stpiArr);

        echo "<br>";
    }
}

echo "array length = ".count($stpiArr);

function ImportExcel($devDB, $pathname, $excel_name, &$stpiArr)
{
    $stpi_id = substr($excel_name, 0, strpos($excel_name, '_'));
    // echo $stpi_id.", ".$excel_name;
    echo $stpi_id;

    if (strlen($stpi_id) > 15) {
        echo "  [有問題ID = ".$stpi_id."]  ";
        return false;
    }


    if(in_array($stpi_id, $stpiArr)){
        echo "  [重複ID = ".$stpi_id."]  ";
        return false;
    } else {
        array_push($stpiArr, $stpi_id);
    }

    // $excelHandler = new ExcelToSQL($devDB);
    // $excelHandler->setExcelFile($pathname);
    // $excelHandler->setSTPI_ID($stpi_id);

    // $excelHandler->handleExcelFile();
    // unset($excelHandler);
}

$time_elapsed_us = microtime(true) - $handle_start;
echo "<p>處理".($i-1)."份 Excel 資料，花費時間 = ".$time_elapsed_us."</p>";

// UPDATE technical_report SET `root_node`='請求項是否與標準規格相符合一覽表(星號為獨立項)' WHERE `root_node`='請求項是否與標準規格相符合一覽表(粗體底線為獨立項)'