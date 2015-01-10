<?php
include_once 'Classes/PHPExcel.php';
include_once 'sql/StoresDB.php';

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

    function handleExcelFile($sheetIndex=0,$sheetEnd=-1,$rowIndex=2) {
        if($this->file == "") return 0;

        $storeDB = new StoresDB($this->dbh);
        $StoreObj = $this->CreateStoresObject();
        $SalseObj = $this->CreateSalseObject();
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

        //手動設定讀取範圍
        $sheetIndex = 2;
        $sheetEnd = 2;

        //依分頁讀取資料
        for($i = $sheetIndex ; $i <= $sheetEnd; $i++){
            $activeSheet = $PHPExcel->getSheet($i); //取得目前分頁
            $sheetData = $activeSheet->toArray(null,true,true,true);
            $sheetName = $activeSheet->getTitle();
            // if ($sheetName != "台北") break;

            $highestRow = $activeSheet->getHighestRow();
            echo "讀取最高行數 = ".$highestRow.", 分頁名稱 = ".$sheetName."<br/>";
            //手動設定讀取範圍
            // if($highestRow > 10)
            //     $highestRow = 10;

            for($j = $rowIndex ; $j <= $highestRow; $j++){
                echo "第".$j."行資料";

                if (!$this->isStoreActivate(trim( $activeSheet->getCell("A"."$j")->getValue())))
                    break;

                $this->getStoreBasicInfo($StoreObj, $activeSheet, $j);
                $this->getStoreAddress($StoreObj, $activeSheet, $j);
                $this->getStoreAdditional($StoreObj, $activeSheet, $j);
                // 店家分類
                $cateAry = $this->getStoreCategory( trim($activeSheet->getCell("H"."$j")->getValue()) );

                $this->getSalesData($SalseObj, $activeSheet, $j);
                if($rCount > 9){
                    $rCount++;
                    break;
                }
                echo "<pre>";
                print_r($StoreObj);
                print_r($SalseObj);
                if(count($cateAry) == 0) echo "找不到分類";
                echo "</pre>";
            }
        }
    }

    /**
     * 讀取Excel中店家額外資料(營業時間,公休日,付款方式,客均價,上下架日期)
     * @param &stdClass 店家物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row 
     * @return none
     */
    function getStoreAdditional(&$StoreObj, $activeSheet, $j) {
        // 營業時間先不處理, 工讀生手動上資料
        $StoreObj->operate_time = "";
        // 公休日
        $StoreObj->rest_time  = trim( $activeSheet->getCell("AI"."$j")->getValue() );
        // 付款方式, 預設現金
        $StoreObj->pay_way    = "cash";
        // 客均價先不處理, 工讀生手動上資料
        $StoreObj->price      = "";
        // 上架日期(合約開始日)
        $StoreObj->start_date = $this->getTimeStamp(trim($activeSheet->getCell("AA"."$j")->getFormattedValue()));
        // 下架日期(合約結束日)
        $StoreObj->end_date   = $this->getTimeStamp(trim($activeSheet->getCell("AB"."$j")->getFormattedValue()));
    }

    /**
     * 讀取Excel中店家額外資料(營業時間,公休日,付款方式,客均價)
     * @param &stdClass 店家物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row 
     * @return none
     */
    function getStoreCategory($category_str) {
        $tempAry = explode(',', $category_str);
        $cateAry = array();
        foreach ($tempAry as $key => $value) {
            if( ($cid = $this->getCategoryID(trim($value))) !== "" ) {
                array_push($cateAry, $cid);
            }
        }
        return (count($cateAry) > 0) ? $cateAry: null;
    }

    /**
     * 取得餐廳種類id
     * @param category_name 餐廳種類名稱
     * @return cid 餐廳種類id
     */
    function getCategoryID($category_name) {
        $cid = "";
        $sql = "SELECT * FROM `categories` WHERE name = :name";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':name', $category_name, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $cid = $row['id'];
        }
        return $cid;
    }

    /**
     * 讀取Excel中店家縣市區域資料
     * @param &stdClass 店家物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row 
     * @return none
     */
    function getStoreAddress(&$StoreObj, $activeSheet, $j) {
        // 店家代碼
        $store_code = trim( $activeSheet->getCell("B"."$j")->getValue() );
        $zipcode = substr($store_code, 2, 3);
        $this->setCityArea($StoreObj, $zipcode);

        // 地址暫時不處理，請工讀生日後手動填入
        $zipcode = $this->getZIPCode( trim($activeSheet->getCell("K"."$j")->getValue()) );
        $StoreObj->address = trim( $activeSheet->getCell("K"."$j")->getValue() );
    }

    /**
     * 讀取表格中店家基本資料
     * @param &stdClass 店家物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row 
     * @return none
     */
    function getStoreBasicInfo(&$StoreObj, $activeSheet, $j) {
        // 店名
        $StoreObj->name     = trim( $activeSheet->getCell("F"."$j")->getValue() );
        // 分店名
        $StoreObj->branch   = trim( $activeSheet->getCell("G"."$j")->getValue() );
        // 電話
        $StoreObj->tel      = trim( $activeSheet->getCell("M"."$j")->getValue() );
    }

    /**
     * 讀取表格中業務系統所需要的資料
     * @param &stdClass 業務物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row 
     * @return none
     */
    function getSalesData(&$SalseObj, $activeSheet, $j) {
        // 店家代碼
        $SalseObj->code          = trim( $activeSheet->getCell("B"."$j")->getValue() );
        // 負責業務
        $SalseObj->running_man   = trim( $activeSheet->getCell("E"."$j")->getValue() );
        // 傳真, 目前合約沒這資料
        $SalseObj->fax           = "";
        // email
        $SalseObj->email         = trim( $activeSheet->getCell("O"."$j")->getValue() );
        // 網站
        $SalseObj->website       = $this->validateURL(trim( $activeSheet->getCell("J"."$j")->getValue()), "Web");
        // FB
        $SalseObj->facebook      = $this->validateURL(trim( $activeSheet->getCell("P"."$j")->getValue()), "FB");
        // 店家聯絡人
        $SalseObj->contact       = trim( $activeSheet->getCell("N"."$j")->getValue() );
        // 店家聯絡人-職稱
        $SalseObj->contact_title = "";
        // 店家聯絡人-電話
        $SalseObj->contact_phone = "";
        // 店家聯絡人-手機
        $SalseObj->contact_mobile= "";
        // 店家聯絡人-email
        $SalseObj->contact_email = trim( $activeSheet->getCell("O"."$j")->getValue() );
        // 店家描述
        $SalseObj->store_description = trim( $activeSheet->getCell("U"."$j")->getValue() );
        // 店家簽約人
        $SalseObj->sign_man      = trim( $activeSheet->getCell("Y"."$j")->getValue() );
        // 店家簽約日
        $SalseObj->sign_date     = $this->getTimeStamp(trim($activeSheet->getCell("Z"."$j")->getFormattedValue()));
    }

    /**
     * 將日期轉為時間戳記
     * @param date_str 日期字串, e.g. "2013/4/19 上午12:00:00 / 41363"
     * @return timestamp 時間戳記
     */
    function getTimeStamp($date_str) {
        if ($date_str == "")
            return "";

        list($month, $day, $year) = explode('-', $date_str);
        $temp = mktime(0, 0, 0, $month, $day, $year);
        return $temp;
    }

    /**
     * 驗證網址，幫忙補上http or https前綴
     * @param url 表格內的網址
     * @param type FB or Web
     * @return none
     */
    function validateURL($url, $type) {
        if(strlen($url) == 0)
            return $url;

        $prefix = ($type == "FB") ? "https://" : "http://";
        $pattern = '/^http(s)?:\/\//';
        if (preg_match($pattern, $url)) {
            return $url;
        } else {
            return $prefix.$url;
        }
    }

    /**
     * 取得縣市id與區域id
     * @param &stdClass 店家物件參考位址
     * @param zipcode 郵遞區號
     * @return none
     */
    function setCityArea(&$StoreObj, $zipcode) {
        $sql = "SELECT * FROM `area_data` WHERE zipcode = :zipcode";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':zipcode', $zipcode, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $StoreObj->city_id = $row['city_id'];
            $StoreObj->area_id = $row['id'];
        }
    }

    /**
     * 取得所屬縣市ID
     * @param city 縣市名稱
     * @return city_id
     */
    function getCityID($city) {
        $id = 30;

        $normal_name = str_replace("臺", "台", $city);
        // echo "改[".$normal_name."]<br>";
        $sql = "SELECT * FROM `city_data` WHERE title like ?";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindValue(1, "$normal_name%", PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $id = $row['id'];
            // echo "縣市ID = ".$id."<br>";
        }
        return $id;
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

    /**
     * 判斷店家上架狀態
     * @param status 表格中的店家狀態, e.g. A,C,X,Z
     * @return bool true:上架, false:下架
     */
    function isStoreActivate($status){
        $status = strtoupper($status);
        if ($status == 'A' || $status == 'Z')
            return true;
        else 
            return false;
    }

    /**
     * 建立Stores Table所需初始資料
     * @return stdClass [stores] Table Object
     */
    function CreateStoresObject() {
        $StoreObj = new stdClass();
        $StoreObj->user_id = 1; //admin
        $StoreObj->pending = 'pending'; //預設下架狀態
        $StoreObj->created_at = time();
        $StoreObj->updated_at = time();
        return $StoreObj;
    }

    /**
     * 建立Salse Table所需初始資料
     * @return stdClass [salse] Table Object
     */
    function CreateSalseObject() {
        $SalseObj = new stdClass();
        $SalseObj->created_at = time();
        $SalseObj->updated_at = time();
        return $SalseObj;
    }

    /**
     * 從Excel檔案路徑取得檔名
     * @param Excel檔案路徑 $excelPath
     * @return string Excel檔名
     */
    function getFileNameFormPath($file_path) {
        $name = substr($file_path, strrpos($file_path, "/")+1);
        //echo "Excel檔名 = ".$excelName."<br/>";
        return $name;
    }
}

?>