<?php
include_once 'Classes/PHPExcel.php';

class ExcelToMySQL {
    var $dbh;
    var $file;

    private $oldData = 0;
    private $newData = 0;

    function __construct($dbh) {
        $this->dbh = $dbh;
    }

    function setExcelFile($filename = "") {
        $this->file = $filename;
    }

    function handleExcelFile($sheetIndex=0,$sheetEnd=-1,$rowIndex=1,$MaxRow=-1) {
        if($this->file == "") return 0;
        try {
            $PHPExcel = PHPExcel_IOFactory::load($this->file);
        } catch(Exception $e) {
            die('Error loading file "'.pathinfo($excelName,PATHINFO_BASENAME).'": '.$e->getMessage());
        }

        //店家數
        $rCount = 0;

        //讀取工作表數目
        $sheetCount = $PHPExcel->getSheetCount();
        if($sheetEnd == -1)
            $sheetEnd = $sheetCount-1;
        //echo "分頁數 = ".$sheetEnd."<br/>";
        
        //依分頁讀取資料
        for($i = $sheetIndex ; $i <= $sheetEnd; $i++){
            $activeSheet = $PHPExcel->getSheet($i); //取得目前分頁
            $sheetData = $activeSheet->toArray(null,true,true,true);
            $sheetName = $activeSheet->getTitle();

            $highestRow = $activeSheet->getHighestRow();
            if($MaxRow != -1)
                $highestRow = $MaxRow; //取得總行數
            echo "讀取最高行數 = ".$highestRow.", 分頁名稱 = ".$sheetName."<br/>";

            //循環讀取excel分頁資料,讀取一條,插入一條
            for($j = $rowIndex ; $j <= $highestRow; $j++){
                echo "第".$j."行最大欄位數 = ".count($sheetData[$j]).", ";
                foreach ($sheetData[$j] as $colkey => $colvalue) {
                    echo "{$colkey}{$j} = {$colvalue}, ";
                }
                echo "<br>";
            }
        }

        //列印每一行的資料
        // echo "<h2>列印每一行的資料</h2>";
        // foreach($sheetData as $row => $col)
        // {
        //     echo "行{$row}: ";
        //     foreach ($col as $colkey => $colvalue) {
        //         echo "{$colvalue}, ";
        //     } 
        //     echo "<br/>";
        // }
        // echo "<hr />";
        
        //取得欄位與行列的值
        // echo "<h2>取得欄位與行列的值</h2>";
        // foreach($sheetData as $row => $col)
        // {
        //     foreach ($col as $colkey => $colvalue) {
        //         echo "{$colkey}{$row} = {$colvalue}, ";
        //     }
        //     echo "<br />";
        // }
    }

    /**
     * 從Excel檔案路徑取得檔名
     * @param Excel檔案路徑 $excelPath
     * @return string Excel檔名
     */
    function getFileNameFormPath($file_path){
        $name = substr($file_path, strrpos($file_path, "/")+1);
        //echo "Excel檔名 = ".$excelName."<br/>";
        return $name;
    }
}

?>