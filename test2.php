<?php
    // 用來檢查店家代碼是否重複
    require 'include/config.php';
    require 'include/sql/StoreNumDB.php';
    include 'include/Classes/PHPExcel.php';
    ini_set('date.timezone','Asia/Taipei');

    $StoreNumDB = new StoreNumDB($devDB);
    $PHPExcel = null;
    $insertCount = 0;
    $repeatCount = 0;

    $file = "合約代碼檢查.xlsx";
    try {
        $PHPExcel = PHPExcel_IOFactory::load($file);
    } catch(Exception $e) {
        die('Error loading file "'.pathinfo($file,PATHINFO_BASENAME).'": '.$e->getMessage());
    }

    //讀取工作表分頁數
    $sheetCount = $PHPExcel->getSheetCount();

    for($i = 0; $i < $sheetCount; $i++){
    // $start = 5;
    // $end = $start + 1;
    // for($i = $start; $i < $end; $i++){
        //讀取第一個分頁資料, 分頁名稱
        $activeSheet = $PHPExcel->getSheet($i);
        $sheetName = $activeSheet->getTitle();

        $highestRow = $activeSheet->getHighestRow();
        echo "<h3>分頁名稱 [".$sheetName."], 最高行數 = ".$highestRow."</h3>";

        getEachSheet($activeSheet, $highestRow);
    }


    function getEachSheet($activeSheet, $highestRow){
        global $insertCount, $repeatCount;
        global $StoreNumDB;
        $rowIndex = 2;
        // $rowIndex = 240;
        // $highestRow = $rowIndex + 0;

        for($j = $rowIndex ; $j <= $highestRow; $j++){
            $NumObj = new stdClass();
            $NumObj->store_num = trim($activeSheet->getCell("B"."$j")->getValue());
            $NumObj->store_name = trim($activeSheet->getCell("F"."$j")->getValue());
            $NumObj->branch_name = trim($activeSheet->getCell("G"."$j")->getValue());

            if ($NumObj->store_num == ""){
                continue;
            } else {
                if (getStoreIDByNum($NumObj->store_num)){
                    $repeatCount++;
                    // 重複店家代碼
                    echo "店家代碼重複, 第 ".$j." 行資料, 店名: ".$NumObj->store_name;
                    echo ", 分店名:".$NumObj->branch_name."<br>";
                } else {
                    if($id = $StoreNumDB->insert($NumObj)) {
                        $insertCount++;
                    } else {
                        echo "新增失敗, 第".$j."行資料, 店名:".$NumObj->store_name;
                        echo ", 分店名:".$NumObj->branch_name."<br>";
                    }
                }
            }

            unset($NumObj);
        }
    }

    function getStoreIDByNum($num) {
        global $devDB;
        $sql = "SELECT * FROM `store_num` WHERE store_num = :store_num";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':store_num', $num, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $id = $row['id'];
        }
        if (isset($id)) {
            return $id;
        } else {
            return false;
        }
    }

    closeDevDBConnection();
?>