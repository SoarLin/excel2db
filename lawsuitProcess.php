<?php
require_once 'include/config.php';
// require_once 'include/ExcelToMySQL.php';
require_once 'include/ExcelToSQL.php';
ini_set('date.timezone','Asia/Taipei');

define('SITE_ROOT', realpath(dirname(__FILE__)));

$path = 'lawsuit_report/';
$Iterator  = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD);

$i = 1;
$handle_start = microtime(true);
$processd_folder = array();
foreach($Iterator as $name => $object) {
    if ($object->isFile() && strpos($object->getFilename(), 'xls')) {

        $pathname = $object->getPathname();
        $names = explode('/', $pathname);

        $folder_name = $names[1];
        $excel_name = $object->getFilename();
        $excel_name = str_replace(" ", "_", $excel_name);

        if (in_array($names[1], $processd_folder)) {
            // 同目錄狀態, 檔案紀錄檢查
            if (in_array($excel_name, $processd_file)) {
                // 同目錄下, 檔案出現重複
                echo $names[1]."/ ";
                echo "重複名稱 : ".$excel_name."<br>";
                continue;
            } else {
                // 同目錄下, 檔案未重複, 將檔名紀錄到陣列
                $processd_file[] = $excel_name;
                echo $i++.". ";
                ImportExcel($devDB, $pathname, $folder_name, $excel_name);
            }
        } else {
            // 換目錄, 目錄名稱塞入陣列, 檔名紀錄重設
            $processd_folder[] = $names[1];
            // unset($processd_file);
            $processd_file = array($excel_name);
            echo $i++.". ";
            ImportExcel($devDB, $pathname, $folder_name, $excel_name);
        }

        echo "<br>";
    }
}

function ImportExcel($devDB, $pathname, $folder_name, $excel_name)
{
    $stpi_id      = substr($folder_name, 0, strpos($folder_name, '_'));
    $lawsuit_id   = substr($excel_name, 0, strpos($excel_name, "_"));
    $name_start   = strpos($excel_name, "_")+1;
    $name_end     = strpos($excel_name, "_閱讀報告");
    $lawsuit_name = substr($excel_name, $name_start, ($name_end - $name_start));
    echo $stpi_id.", ".$lawsuit_id.", ".$lawsuit_name;

    $excelHandler = new ExcelToSQL($devDB);
    $excelHandler->setExcelFile($pathname);
    $excelHandler->setSTPI_ID($stpi_id);
    $excelHandler->setLawsuitID($lawsuit_id);
    $excelHandler->setLawsuitName($lawsuit_name);

    $excelHandler->handleExcelFile();
    unset($excelHandler);
}

$time_elapsed_us = microtime(true) - $handle_start;
echo "<p>處理".($i-1)."份 Excel 資料，花費時間 = ".$time_elapsed_us."</p>";