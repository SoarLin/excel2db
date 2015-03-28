<?php
    require 'include/config.php';
    require 'include/sql/ListDB.php';
    require 'include/sql/CardOrderDB.php';
    include 'include/Classes/PHPExcel.php';
    ini_set('date.timezone','Asia/Taipei');

    $CardDB = new CardOrderDB($devDB);

    $PHPExcel = null;
    $insertCount = 0;
    $updateCount = 0;

    $file = "preorder_miss_address0327.xlsx";
    try {
        $PHPExcel = PHPExcel_IOFactory::load($file);
    } catch(Exception $e) {
        die('Error loading file "'.pathinfo($file,PATHINFO_BASENAME).'": '.$e->getMessage());
    }
    //讀取工作表分頁數
    $sheetCount = $PHPExcel->getSheetCount();
    //讀取第一個分頁資料, 分頁名稱
    $activeSheet = $PHPExcel->getSheet(0);
    $sheetName = $activeSheet->getTitle();

    $highestRow = $activeSheet->getHighestRow();
    echo "<p>分頁名稱 [".$sheetName."], 最高行數 = ".$highestRow."</p>";

    $rowIndex = 3;
    // $rowIndex = 240;
    $highestRow = $rowIndex + 10;
    for($j = $rowIndex ; $j < $highestRow; $j++){
    // for($j = $rowIndex ; $j <= $rowIndex; $j++){

        $CardObj = new stdClass();
        $CardObj->username = trim($activeSheet->getCell("E"."$j")->getValue());
        $CardObj->userphone = trim($activeSheet->getCell("F"."$j")->getValue());
        $CardObj->useremail = trim($activeSheet->getCell("G"."$j")->getValue());
        $CardObj->address = trim($activeSheet->getCell("H"."$j")->getValue());

        if ($ID = GetCardOrderID($CardObj->username)){
              $zip = null;
              echo "ID = ".$ID.", 姓名:".$CardObj->username." ";
              if ($zip = getZIPCode($CardObj->address)){
                  $zipcode = substr($zip, 0, 3);
                  $CardObj->zipcode = $zipcode;
                  $CardObj->address = $zip." ".$CardObj->address;

                  echo "郵遞區號＋地址 : ".$CardObj->address."<br><br>";
                  $CardDB->update($ID, $CardObj);
              } else {
                  echo "找不到的郵遞區號";
                  echo "地址 : ".$CardObj->address."<br><br>";
              }
              sleep(1);
        } else {
            echo "找不到的名字 ".$CardObj->username."<br>";
            // var_dump($CardObj);
        }
    }


    function GetCardOrderID($name) {
        global $devDB;
        $sql = "SELECT * FROM `card_order` WHERE username = :username";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':username', $name, PDO::PARAM_STR);
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

    function getZIPCode($address) {
        // 地址暫時不處理，請工讀生日後手動填入
        $json = file_get_contents('http://zip5.5432.tw/zip5json.py?adrs='.$address);
        $zipcode = json_decode($json)->{'zipcode'};
        if (strlen($zipcode) == 0) {
            return false;
        } else {
            return $zipcode;
        }
    }