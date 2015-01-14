<?php
include_once 'Classes/PHPExcel.php';
include_once 'sql/StoresDB.php';
include_once 'sql/SalesDB.php';

class ExcelToMySQL {
    var $dbh;
    var $file;

    private $oldData = 0;
    private $newData = 0;
    private $error_array = [];

    function __construct($dbh) {
        $this->dbh = $dbh;
    }

    function setExcelFile($filename = "") {
        $this->file = $filename;
    }

    function handleExcelFile($rowIndex=3) {
        if($this->file == "") return 0;

        $salesDB = new SalesDB($this->dbh);
        $storeDB = new StoresDB($this->dbh);
        try {
            $PHPExcel = PHPExcel_IOFactory::load($this->file);
        } catch(Exception $e) {
            die('Error loading file "'.pathinfo($excelName,PATHINFO_BASENAME).'": '.$e->getMessage());
        }

        //店家數
        $rCount = 0;

        //讀取工作表數目
        $sheetCount = $PHPExcel->getSheetCount();

        //讀取第一個分頁資料
        $activeSheet = $PHPExcel->getSheet(0);
        $sheetName = $activeSheet->getTitle();
        // if ($sheetName != "台北") break;

        $highestRow = $activeSheet->getHighestRow();
        echo "分頁名稱 = ".$sheetName.", 最高行數 = ".$highestRow."(只讀取前100筆資料)<br/>";
        //手動設定讀取範圍
        if($highestRow > 102)
            $highestRow = 102   ;

        // for($j = $rowIndex ; $j <= $highestRow; $j++){
        for($j = $rowIndex ; $j <= $rowIndex+4; $j++){
            echo "第".$j."行資料, ";
            if ( trim($activeSheet->getCell("H"."$j")->getValue()) == "" ){
                $error_array[] = $j;
                break;
            }

            $SalesObj = $this->CreateSalesObject();

            // 抓取業務表格資料
            $this->getSalesData($SalesObj, $activeSheet, $j);

            // 新增資料前先檢查是否已存在，新增後取回 sales_id
            $checkObj = new stdClass();
            $checkObj->store_num = $SalesObj->store_num;
            $sales_id = $this->CheckAndInsert($salesDB, $checkObj, $SalesObj);
            // echo "sales_id = ".$sales_id."<br>";
            $result = $this->handleSalesTimeStampField($salesDB, $sales_id, $activeSheet, $j);
            $result = $salesDB->updateLocation($sales_id, 'location', $SalesObj->lat, $SalesObj->lng);

            // 透過店家代號，找店家id
            $store_id = $storeDB->getIdByData($checkObj);
            // echo "store_id = ".$store_id."<br>";
            // 檢查 stores 中 id 是否有資料已經與 sales_id 相同
            // 檢查店家狀態，再新增到 stores 表格中
            if ( ($sales_id != $store_id) && $this->isStoreActivate($SalesObj->status) ){
                $StoreObj = $this->CreateStoresObject();
                // 設定要存到店家表格的資料
                $this->setStoreData($StoreObj, $SalesObj, $sales_id);

                // 新增資料
                $store_id = $storeDB->insert($StoreObj);
                $result = $this->handleStoresTimeStampField($storeDB, $store_id, $activeSheet, $j);
                $result = $storeDB->updateLocation($store_id, 'location', $StoreObj->lat, $StoreObj->lng);
                echo "<pre> 店家表格<br>";
                var_dump($StoreObj);
                echo "</pre>";
            }

            // 新增 店家<->餐廳類型 關聯表

            // 新增 店家<->標籤 關聯表


            $rCount++;
            echo "<pre> 業務表格<br>";
            var_dump($SalesObj);
            echo "</pre>";
        }

    }


    function handleSalesTimeStampField($salesDB, $id, $activeSheet, $j) {
        $timeObj = new stdClass();
        // 創建時間
        $timeObj->created_at = time();
        // 更新時間
        $timeObj->updated_at = time();
        // 店家簽約日
        $timeObj->sign_date  = $this->getTimeStamp(trim($activeSheet->getCell("A"."$j")->getFormattedValue()));
        // 上架日期(合約開始日), 下架日期(合約結束日)
        $timeObj->start_date = $this->getTimeStamp(trim($activeSheet->getCell("B"."$j")->getFormattedValue()));
        $timeObj->end_date   = $this->getTimeStamp(trim($activeSheet->getCell("C"."$j")->getFormattedValue()));
        return $salesDB->updateTimestampField($id, $timeObj);
    }

    function handleStoresTimeStampField($storeDB, $id, $activeSheet, $j) {
        $timeObj = new stdClass();
        $timeObj->created_at = time();
        $timeObj->updated_at = time();
        $timeObj->start_date = $this->getTimeStamp(trim($activeSheet->getCell("B"."$j")->getFormattedValue()));
        $timeObj->end_date   = $this->getTimeStamp(trim($activeSheet->getCell("C"."$j")->getFormattedValue()));
        return $storeDB->updateTimestampField($id, $timeObj);
    }

    /**
     * 讀取表格中業務系統所需要的資料
     * @param &stdClass 業務物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row
     * @return none
     */
    function getSalesData(&$SalesObj, $activeSheet, $j) {
        // 紙本合約備註
        $SalesObj->contract_note = trim( $activeSheet->getCell("D"."$j")->getValue() );
        // 負責業務
        $SalesObj->sales         = trim( $activeSheet->getCell("E"."$j")->getValue() );
        // 合作狀態
        $SalesObj->status        = trim( $activeSheet->getCell("F"."$j")->getValue() );
        // 連鎖品牌代碼, 店家代碼
        $SalesObj->chain_num     = trim( $activeSheet->getCell("G"."$j")->getValue() );
        $SalesObj->store_num     = trim( $activeSheet->getCell("H"."$j")->getValue() );
        // 店家名稱, 分店名稱
        $SalesObj->name          = trim( $activeSheet->getCell("I"."$j")->getValue() );
        $SalesObj->branch        = trim( $activeSheet->getCell("J"."$j")->getValue() );
        // 縣市, 行政區, 地址
        $city = trim($activeSheet->getCell("K"."$j")->getValue());
        $area = trim($activeSheet->getCell("L"."$j")->getValue());
        $SalesObj->city_id       = $this->getCityID($city);
        $SalesObj->area_id       = $this->getAreaID($area);
        $SalesObj->address       = trim( $activeSheet->getCell("M"."$j")->getValue() );
        $this->setLongLat($city, $area, $SalesObj);
        // 電話
        $SalesObj->phone         = trim( $activeSheet->getCell("N"."$j")->getValue() );
        // 營業時間先不處理, 工讀生手動上資料
        $SalesObj->operate_time  = ""; //trim( $activeSheet->getCell("O"."$j")->getValue() );
        // 公休日
        $SalesObj->rest_time     = $this->reGenerateRestTime( trim($activeSheet->getCell("P"."$j")->getValue()) );
        // 官方網站, FB粉絲團
        $SalesObj->website       = $this->validateURL(trim( $activeSheet->getCell("Q"."$j")->getValue()), "Web");
        $SalesObj->facebook      = $this->validateURL(trim( $activeSheet->getCell("R"."$j")->getValue()), "FB");
        // 受訪意願
        $SalesObj->interview     = $this->checkInterviewStatus( trim($activeSheet->getCell("S"."$j")->getValue()) );
        // 店家聯絡人, 職稱, 電話, 手機, email
        $SalesObj->contact       = trim( $activeSheet->getCell("U"."$j")->getValue() );
        $SalesObj->contact_title = trim( $activeSheet->getCell("V"."$j")->getValue() );
        $SalesObj->contact_phone = trim( $activeSheet->getCell("W"."$j")->getValue() );
        $SalesObj->contact_mobile= trim( $activeSheet->getCell("X"."$j")->getValue() );
        $SalesObj->contact_email = trim( $activeSheet->getCell("Y"."$j")->getValue() );
        // 客均價
        $SalesObj->price         = $this->getPriceRange( trim($activeSheet->getCell("AF"."$j")->getValue()) );
        // 付款方式
        $SalesObj->pay_way       = trim( $activeSheet->getCell("AG"."$j")->getValue() );
        // 發票抬頭, 統一編號, 發票類型, 發票寄送地址
        $SalesObj->invoice_title = trim( $activeSheet->getCell("AI"."$j")->getValue() );
        $SalesObj->invoice_num   = trim( $activeSheet->getCell("AJ"."$j")->getValue() );
        $SalesObj->invoice_type  = trim( $activeSheet->getCell("AK"."$j")->getValue() );
        $SalesObj->invoice_address=trim( $activeSheet->getCell("AL"."$j")->getValue() );
        // 店家描述
        $SalesObj->summary       = trim( $activeSheet->getCell("AV"."$j")->getValue() );
        // 店家簽約人
        $SalesObj->sign_man      = trim( $activeSheet->getCell("AW"."$j")->getValue() );

    }

    function setStoreData(&$StoreObj, $SalesObj, $sales_id) {
        $StoreObj->id            = $sales_id;
        $StoreObj->store_num     = $SalesObj->store_num;
        $StoreObj->name          = $SalesObj->name;
        $StoreObj->branch        = $SalesObj->branch;
        $StoreObj->tel           = $SalesObj->phone;
        $StoreObj->city_id       = $SalesObj->city_id;
        $StoreObj->area_id       = $SalesObj->area_id;
        $StoreObj->address       = $SalesObj->address;
        $StoreObj->lng           = $SalesObj->lng;
        $StoreObj->lat           = $SalesObj->lat;
        // $StoreObj->location      = $SalesObj->location;
        $StoreObj->operate_time  = $SalesObj->operate_time;
        $StoreObj->rest_time     = $SalesObj->rest_time;
        $StoreObj->price         = $SalesObj->price;
        $StoreObj->status        = "published";
    }

    /**
     * 檢查資料不存在就新增置資料庫
     * @param 資料庫操作元件 $op
     * @param stdClass 檢查的物件 $checkObj
     * @param stdClass 新增的物件 $insertObj
     * @return 該筆資料ID
     */
    function CheckAndInsert($db, $checkObj, $insertObj){
        $ID = $db->getIdByData($checkObj);
        if($ID){
            $this->oldData++;
            $db->update($ID, $insertObj);
            // echo "資料已存在, ID = ".$ID."<br/>";
        } else {
            $this->newData++;
            $ID = $db->insert($insertObj);
            // echo "新增資料, ID = ".$ID."<br/>";
            // echo "新增資料, ID = <br/>";
            //$this->printObj($insertObj);
        }
        return $ID;
    }


    /**
     * 取得餐廳類型陣列
     * @param category_str 類型字串
     * @return array 餐廳類型陣列
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
     * 將日期轉為時間戳記
     * @param date_str 日期字串, e.g. "2013/4/19 上午12:00:00 / 41363"
     * @return timestamp 時間戳記
     */
    function getTimeStamp($date_str) {
        if ($date_str == "")
            return "";

        list($year, $month, $day) = explode('/', $date_str);
        $temp = mktime(0, 0, 0, $month, $day, $year);
        return $temp;
    }

    /**
     * 重組公休日字串，透過半形逗號切開，重新以", "組合
     * @param rest_time 公休日字串
     * @return string 公休日字串
     */
    function reGenerateRestTime($rest_time) {
        $str_array = explode(",", $rest_time);
        foreach ($str_array as $i => $val) {
            $str_array[$i] = trim($val);
        }
        $new_rest_time = implode(", ", $str_array);
        return $new_rest_time;
    }

    /**
     * 檢查受訪意願，欄位內有資料表示有意願
     * @param interview 受訪意願欄位資料
     * @return int 0:沒意願,1:有意願
     */
    function checkInterviewStatus($interview) {
        return (strlen($interview) == 0) ? 0 : 1;
    }

    /**
     * 取得價格區間對應數值
     * @param price 價格區間字串
     * @return int 0:~199, 1:200~499, 2:500~999, 3:1000~
     */
    function getPriceRange($price) {
        if ($price == "低於199") {
            return 0;
        } else if ($price == "200～499") {
            return 1;
        } else if ($price == "500～999") {
            return 2;
        } else if ($price == "高於1000") {
            return 3;
        }
        return 0;
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
        // $normal_name = str_replace("臺", "台", $city);
        // echo "改[".$normal_name."]<br>";
        $sql = "SELECT * FROM `city_data` WHERE title = :title";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':title', $city, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $id = $row['id'];
            // echo "縣市ID = ".$id."<br>";
        }
        return (int)$id;
    }

    /**
     * 取得所屬行政區ID
     * @param area 行政區名稱
     * @return area_id
     */
    function getAreaID($area) {
        $id = 0;

        $sql = "SELECT * FROM `area_data` WHERE title = :title";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':title', $area, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $id = $row['id'];
            // echo "行政區ID = ".$id."<br>";
        }
        return (int)$id;
    }

    /**
     * 設定經緯度資料
     * @param city 縣市
     * @param area 行政區
     * @param &stdClass 業務物件參考位址
     * @return none
     */
    function setLongLat($city, $area, &$SalesObj) {
        $full_address = $city.$area.$SalesObj->address;
        $url = "http://maps.google.com/maps/api/geocode/json?address=".$full_address."&sensor=false&region=TW";
        $response = file_get_contents($url);
        $response = json_decode($response, true);
        // 經度
        $SalesObj->lng = $response['results'][0]['geometry']['location']['lng'];
        // 緯度
        $SalesObj->lat = $response['results'][0]['geometry']['location']['lat'];
        // $SalesObj->location = "PointFromText('POINT($SalesObj->lat $SalesObj->lng)')";
    }

    function updateLongLat($city, $area, $sales_id) {
        $full_address = $city.$area.$SalesObj->address;
        $url = "http://maps.google.com/maps/api/geocode/json?address=".$full_address."&sensor=false&region=TW";
        $response = file_get_contents($url);
        $response = json_decode($response, true);
        // 經度
        $SalesObj->lng = $response['results'][0]['geometry']['location']['lng'];
        // 緯度
        $SalesObj->lat = $response['results'][0]['geometry']['location']['lat'];
        $SalesObj->location = "PointFromText('POINT($SalesObj->lat $SalesObj->lng)')";
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
        // $status = strtoupper($status);
        $status = explode(" ", $status);
        // echo ", 狀態=".$status[0]."<br>";
        if ($status[0] == 'A' || $status[0] == 'Z')
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
        // $StoreObj->created_at = time();
        // $StoreObj->updated_at = time();
        return $StoreObj;
    }

    /**
     * 建立Sales Table所需初始資料
     * @return stdClass [Sales] Table Object
     */
    function CreateSalesObject() {
        $SalesObj = new stdClass();
        return $SalesObj;
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