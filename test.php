<?php
    require 'include/config.php';
    require 'include/sql/CardOrderDB.php';
    include 'include/Classes/PHPExcel.php';
    ini_set('date.timezone','Asia/Taipei');

    $PHPExcel = null;
    $insertCount = 0;
    $updateCount = 0;
    $CardDB = new CardOrderDB($devDB);

    $file = "10312120075.xlsx";
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

    $rowIndex = 2;
    // $highestRow = 20;
    for($j = $rowIndex ; $j <= $highestRow; $j++){
    // for($j = $rowIndex ; $j <= $rowIndex; $j++){

        $CardObj = new stdClass();
        $CardObj->sun_no = trim($activeSheet->getCell("A"."$j")->getValue());
        $CardObj->eatme_no = trim($activeSheet->getCell("C"."$j")->getValue());
        $CardObj->card_amount = getCardAmount($activeSheet->getCell("L"."$j")->getValue());
        $CardObj->amount = trim($activeSheet->getCell("E"."$j")->getValue());
        $CardObj->is_oldMember = 1;
        $CardObj->pay_method = getPayMethod($CardObj->sun_no);
        $CardObj->note1 = trim($activeSheet->getCell("O"."$j")->getValue()).", ".
                        trim($activeSheet->getCell("P"."$j")->getValue());
        $CardObj->is_paid = checkIsPaid($activeSheet->getCell("H"."$j")->getValue());
        $CardObj->err_code = ($CardObj->is_paid) ? "00" : $activeSheet->getCell("H"."$j")->getValue();
        $CardObj->err_msg = getErrorMessage($activeSheet->getCell("I"."$j")->getValue(),
                                            $CardObj->is_paid);
        $CardObj->approve_code = trim($activeSheet->getCell("K"."$j")->getValue());
        $CardObj->card_no = trim($activeSheet->getCell("J"."$j")->getValue());
        $CardObj->card_user = trim($activeSheet->getCell("G"."$j")->getValue());
        $CardObj->pay_date = setMySQLDATETIME($activeSheet->getCell("D"."$j")->getValue());

        // echo $CardObj->card_amount.":".$CardObj->amount."<br>";
        // var_dump($CardObj);

        if ($CardObj->is_paid == 1){
            // checkAndInsert($CardObj);
            if( $id = getCardOrderId($CardObj->eatme_no) ){
                // update
                $CardDB->update($id, $CardObj);
                $updateCount++;
            } else {
                // insert
                $id = $CardDB->insert($CardObj);
                $insertCount++;
            }
        }
    }

    echo "新增資料筆數 : ".$insertCount."<br>";
    echo "更新資料筆數 : ".$updateCount."<br>";


    function checkAndInsert($CardObj) {

    }

    function getCardOrderId($nomber){
        global $devDB;
        $sql = "SELECT * FROM `card_order` WHERE eatme_no = :eatme_no";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':eatme_no', $nomber, PDO::PARAM_STR);
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

    // 略過單引號
    function skeepSingleQuotes($str) {
        $str_trim = trim($str);
        $result = str_replace("'", "", $str_trim);
        return $result;
    }

    // 取出卡片張數
    function getCardAmount($str) {
        $temp = skeepSingleQuotes($str);
        $index = strpos($temp, " ");
        $end   = strpos($temp, "張");
        $amount = substr($temp, $index + 1, ($end - $index - 1));
        return $amount;
    }

    function getPayMethod($str) {
        $first = substr($str, 0, 1);
        if ($first == "E"){
            return 1;
        } else if ($first == "9") {
            return 3;
        } else if ($first == "4") {
            return 4;
        }
        return "";
    }

    function checkIsPaid($str) {
        $temp = trim($str);
        if ($temp == "0") {
            return 1;
        } else {
            return 0;
        }
    }

    function getErrorMessage($str, $is_paid) {
        $temp = trim($str);
        if ($is_paid && strlen($temp) == 0) {
            return "交易成功";
        } else {
            return $temp;
        }
    }

    function setMySQLDATETIME($date) {
        $date = trim($date);
        $date = PHPExcel_Style_NumberFormat::toFormattedString($date, 'YYYY-MM-DD hh:mm:ss');
        // echo "日期 = ".$date.", ";
        if (strlen($date) == 0)
            return NULL;
        else
            return date("Y-m-d H:i:s", strtotime($date));
    }
?>