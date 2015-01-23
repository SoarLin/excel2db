<?php
include_once 'Classes/PHPExcel.php';
include_once 'sql/StoresDB.php';
include_once 'sql/SalesDB.php';
include_once 'sql/StoreCategoryDB.php';
include_once 'sql/StorePickDB.php';

class ExcelToMySQL {
    var $dbh;
    var $file;
    var $PHPExcel;

    private $sheetCount;    // Excel 分頁數量
    private $activeSheet;   // 正在作用中的
    private $sheetName;

    private $oldData = 0;
    private $newData = 0;
    private $error_array = [];
    private $skeep = false;

    function __construct($dbh) {
        $this->dbh = $dbh;
    }

    function setExcelFile($filename = "") {
        $this->file = $filename;
        try {
            $this->PHPExcel = PHPExcel_IOFactory::load($this->file);
        } catch(Exception $e) {
            die('Error loading file "'.pathinfo($excelName,PATHINFO_BASENAME).'": '.$e->getMessage());
        }
        //讀取工作表分頁數
        $this->sheetCount = $this->PHPExcel->getSheetCount();
        //讀取第一個分頁資料, 分頁名稱
        $this->activeSheet = $this->PHPExcel->getSheet(0);
        $this->sheetName = $this->activeSheet->getTitle();
    }

    function handleExcelFile($rowIndex=3) {
        if($this->file == "") return 0;

        $salesDB = new SalesDB($this->dbh);
        $storeDB = new StoresDB($this->dbh);

        //店家數
        $rCount = 0;

        $highestRow = $this->activeSheet->getHighestRow();
        echo "<p>分頁名稱 [".$this->sheetName."], 最高行數 = ".$highestRow."(只讀取前100筆資料)</p>";
        //手動設定讀取範圍
        if($highestRow > 102)
            $highestRow = 102;

        for($j = $rowIndex ; $j <= $highestRow; $j++){
        // for($j = $rowIndex ; $j <= $rowIndex; $j++){
            $start = microtime(true);
            $this->skeep = false;
            // echo "第".$j."行資料, ";
            if ( trim($this->activeSheet->getCell("I"."$j")->getValue()) == "" ) {
                $this->error_array[] = "第".$j."行 沒有店家名稱";
                continue;
            }

            $SalesObj = $this->CreateSalesObject();
            try {
                // 抓取業務表格資料
                $this->getSalesData($SalesObj, $this->activeSheet, $j);

                if (!$this->skeep) {
                    $sales_id = $this->processOneSalesData($salesDB, $SalesObj);
                    $store_id = $this->processOneStoreData($storeDB, $SalesObj, $sales_id);

                    // 新增 店家<->餐廳類型 關聯表
                    $this->updateStoreCategory($store_id, $this->activeSheet, $j);
                    // 新增 店家<->標籤 關聯表
                    $this->updateStorePick($store_id, $this->activeSheet, $j);

                    $rCount++;
                }
            } catch (Exception $e) {
                $error_array[] = $j;
            }

            $time_elapsed_us = microtime(true) - $start;
            // echo "處理第".$j."行資料，總共花費時間 = ".$time_elapsed_us.", 休息0.3秒 <br>";
            unset($SalesObj);
            usleep(300000);
        }

        unset($salesDB);
        unset($storeDB);

        echo "<p>處理總比數：".$rCount.", ";
        echo "新增次數(與資料比數無關)：".$this->newData.", ";
        echo "更新次數(與資料比數無關)：".$this->oldData."</p>";
        if (count($this->error_array) > 0) {
            echo "<pre>";
            foreach ($this->error_array as $i => $v) {
                echo "錯誤資料 = ". $v."<br>";
            }
            echo "</pre>";
        }
        echo "<br>";
    }

    function processOneSalesData($salesDB, $SalesObj) {
        // 檢查店家代碼是否已存在，新增或更新後取回 sales_id 再給店家表格用
        $checkObj = new stdClass();
        $checkObj->store_num = $SalesObj->store_num;
        $sales_id = $this->CheckAndInsert($salesDB, $checkObj, $SalesObj);
        // echo "sales_id = ".$sales_id.", ";
        $salesDB->updateGeomPoint($sales_id, 'location', $SalesObj->lat, $SalesObj->lng);

        unset($checkObj);
        return $sales_id;
    }

    function processOneStoreData($storeDB, $SalesObj, $sales_id) {
        $StoreObj = $this->CreateStoresObject();
        // 設定要存到店家表格的資料
        $this->setStoreData($StoreObj, $SalesObj, $sales_id);

        $checkObj = new stdClass();
        $checkObj->id = $sales_id;
        $store_id = $this->CheckAndInsert($storeDB, $checkObj, $StoreObj);
        // echo "store_id = ".$store_id.", ";
        $storeDB->updateGeomPoint($store_id, 'location', $StoreObj->lat, $StoreObj->lng);

        if ( $this->isStoreActivate($SalesObj->status) == false){
            // echo "要刪除store_id = ".$store_id.", ";
            $storeDB->delete($store_id);
        }

        unset($checkObj);
        unset($StoreObj);
        return $store_id;
    }

    /**
     * 讀取表格中業務系統所需要的資料
     * @param &stdClass 業務物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param j Excel表格中的行數 Row
     * @return none
     */
    function getSalesData(&$SalesObj, $activeSheet, $j) {
        // 店家簽約日, 上架日期(合約開始日), 下架日期(合約結束日)
        $SalesObj->sign_date     = $this->setMySQLDATETIME(trim($activeSheet->getCell("A"."$j")->getValue()));
        $SalesObj->start_date    = $this->setMySQLDATETIME(trim($activeSheet->getCell("B"."$j")->getValue()));
        $SalesObj->end_date      = $this->setMySQLDATETIME(trim($activeSheet->getCell("C"."$j")->getValue()));
        // 紙本合約備註
        $SalesObj->contract_note = trim( $activeSheet->getCell("D"."$j")->getValue() );
        // 負責業務
        $SalesObj->sales         = trim( $activeSheet->getCell("E"."$j")->getValue() );
        // 合作狀態
        $SalesObj->status        = $this->getStatusID( trim($activeSheet->getCell("F"."$j")->getValue()) );
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
        $SalesObj->area_id       = $this->getAreaID($SalesObj->city_id, $area);
        $SalesObj->address       = trim( $activeSheet->getCell("M"."$j")->getValue() );
        // echo "city = ".$city.", area = ".$area."<br>";
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

        if($this->checkStorePhoneIsExist($SalesObj->phone) == ""){
            $SalesObj->store_num = $this->regetStoreNum($SalesObj->store_num,
                                $city.$area.$SalesObj->address, $SalesObj->phone);
        }

        $this->checkIsDataError($SalesObj, $j);
    }

    /**
     * 設定 stores 表格中資料
     * @param &stdClass 店家物件參考位址
     * @param stdClass 業務表格資料
     * @param int sales_id
     * @return none
     */
    function setStoreData(&$StoreObj, $SalesObj, $sales_id) {
        $StoreObj->id            = $sales_id;
        $StoreObj->store_num     = $SalesObj->store_num;
        $StoreObj->name          = $SalesObj->name;
        $StoreObj->branch        = $SalesObj->branch;
        $StoreObj->cover_uuid    = "NoImage";
        $StoreObj->tel           = $SalesObj->phone;
        $StoreObj->city_id       = $SalesObj->city_id;
        $StoreObj->area_id       = $SalesObj->area_id;
        $StoreObj->address       = $SalesObj->address;
        $StoreObj->lng           = $SalesObj->lng;
        $StoreObj->lat           = $SalesObj->lat;
        $StoreObj->operate_time  = $SalesObj->operate_time;
        $StoreObj->rest_time     = $SalesObj->rest_time;
        $StoreObj->price         = $SalesObj->price;
        $StoreObj->status        = "pending";
        // $StoreObj->start_date    = $SalesObj->start_date;
        // $StoreObj->end_date      = $SalesObj->end_date;
    }

    /**
     * 檢查資料不存在就新增置資料庫
     * @param 資料庫操作元件 $op
     * @param stdClass 檢查的物件 $checkObj
     * @param stdClass 新增的物件 $insertObj
     * @return 該筆資料ID
     */
    function CheckAndInsert($db, $checkObj, $insertObj){
        try{
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
            }
            return $ID;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 更新 餐廳-標籤 關聯表
     * @param int 餐廳ID
     * @param SheetData 正在處理的Excel分頁
     * @param int 整在處理行數
     * @return none
     */
    function updateStorePick($store_id, $activeSheet, $j) {
        $StorePickDB = new StorePickDB($this->dbh);
        $storePickObj = new stdClass();
        $storePickObj->store_id = $store_id;

        $pick_index = ["AA", "AC", "AE"];
        foreach ($pick_index as $i => $val) {
            $pick_str = trim($activeSheet->getCell($val.$j)->getValue());
            if (strlen($pick_str) > 0) {
                $pick_name = explode("/", $pick_str)[1];
                $storePickObj->pick_id = $this->getPickID($pick_name);
                $this->CheckAndInsert($StorePickDB, $storePickObj, $storePickObj);
            }
        }
    }

    /**
     * 更新 餐廳-種類 關聯表
     * @param int 餐廳ID
     * @param SheetData 正在處理的Excel分頁
     * @param int 整在處理行數
     * @return none
     */
    function updateStoreCategory($store_id, $activeSheet, $j) {
        $storeCategooryDB = new StoreCategoryDB($this->dbh);
        $storeCateObj = new stdClass();
        $storeCateObj->store_id = $store_id;

        $category_index = ["Z", "AB", "AD"];
        foreach ($category_index as $i => $val) {
            $category_str = trim($activeSheet->getCell($val.$j)->getValue());
            if (strlen($category_str) > 0){
                $storeCateObj->category_id = $this->getCategoryID($category_str);
                $this->CheckAndInsert($storeCategooryDB, $storeCateObj, $storeCateObj);
            }
        }
    }

    /**
     * 檢查資料是否有錯誤
     * @param stdClass 業務物件
     * @param int 整在處理行數
     * @return none
     */
    function checkIsDataError($SalesObj, $j) {
        $msg = "";
        if ($SalesObj->store_num == "") {
            $msg .= "無法產生店家代碼, ";
        }
        if ($SalesObj->status == "") {
            $msg .= "店家狀態有錯, ";
        }
        if ($SalesObj->city_id == "") {
            $msg .= "縣市欄位有錯, ";
        }
        if ($SalesObj->area_id == ""){
            $msg .= "行政區欄位有錯, ";
        }
        if ($SalesObj->price == -1) {
            $msg .= "價格區間有錯, ";
        }
        if(strlen($msg) > 0){
            $this->error_array[] = "第".$j."行 ".substr($msg, 0, strlen($msg)-2);
            $this->skeep = true;
        }
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
     * @param string 餐廳種類名稱
     * @return cid 餐廳種類id
     */
    function getCategoryID($name) {
        $cid = "";
        $sql = "SELECT * FROM `categories` WHERE name = :name";
        // echo $sql."<br>".$name;
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $cid = $row['id'];
        }
        return $cid;
    }

    /**
     * 取得餐廳標籤id
     * @param string 餐廳標籤名稱
     * @return cid 餐廳標籤id
     */
    function getPickID($name) {
        $cid = "";
        $sql = "SELECT * FROM `picks` WHERE name = :name";
        // echo $sql."<br>".$name;
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $cid = $row['id'];
        }
        return $cid;
    }

    /**
     * 取得餐廳狀態id
     * @param string 餐廳狀態字串
     * @return cid 餐廳狀態id
     */
    function getStatusID($status_str) {
        $cid = "";
        $code = explode(" ", $status_str)[0];
        $sql = "SELECT * FROM `store_status` WHERE code = :code";
        // echo $sql."<br>".$name;
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
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
     * 設定日期為MySQL接受格式
     * @param date_str PHPEXcel讀出日期字串, e.g. "41363"
     * @return date 時間格式
     */
    function setMySQLDATETIME($date) {
        $date = PHPExcel_Style_NumberFormat::toFormattedString($date, 'YYYY-MM-DD');
        // echo "日期 = ".$date.", ";
        if (strlen($date) == 0)
            return NULL;
        else
            return date("Y-m-d H:i:s", strtotime($date));
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
        return 1;
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
        $id = "";
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
        return $id;
    }

    /**
     * 取得所屬行政區ID
     * @param area 行政區名稱
     * @return area_id
     */
    function getAreaID($city_id, $area) {
        $id = "";

        $sql = "SELECT * FROM `area_data` WHERE city_id = :city_id AND title = :title";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':city_id', $city_id, PDO::PARAM_STR);
        $stmt->bindParam(':title', $area, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $id = $row['id'];
            // echo "行政區ID = ".$id."<br>";
        }
        return $id;
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
        if ($response['status'] == "OK") {
            // 經度
            $SalesObj->lng = $response['results'][0]['geometry']['location']['lng'];
            // 緯度
            $SalesObj->lat = $response['results'][0]['geometry']['location']['lat'];
        } else {
            echo "抓取經緯度有誤, url = ".$url."<br>";
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

    function regetStoreNum($store_num, $address, $phone) {
        $regex = '/^TW[0-9]{10}$/';
        if (preg_match($regex, $store_num)){
            return $store_num;
        } else {
            $zipcode = $this->getZIPCode($address);
            if ($zipcode == false){
                return "";
            } else {
                return $this->createStoreNum("TW".$zipcode);
            }
        }
    }

    function checkStorePhoneIsExist($phone) {
        $id = "";
        $sql = "SELECT * FROM `sales` WHERE phone = :phone";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $id = $row['id'];
            // echo "ID = ".$id."<br>";
        }
        return ($id != "") ? true : false;
    }

    function createStoreNum($pre_store_num) {
        $sql = "SELECT * FROM `sales` WHERE store_num LIKE ? ORDER BY store_num DESC LIMIT 1";
        $params = array($pre_store_num."%");
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);
        while($row = $stmt->fetch()) {
            $store_num = $row['store_num'];
            // echo "最大的 store_num = ".$store_num."<br>";
        }

        return $this->getNextStoreNum($store_num);
    }

    function getNextStoreNum($store_num) {
        $serial_num = substr($store_num, 7);
        $next_sn = str_pad($serial_num+1, 5, '0', STR_PAD_LEFT);
        $next_store_num = substr($store_num, 0, 7).$next_sn;
        return $next_store_num;
    }

    /**
     * 判斷店家上架狀態
     * @param status 表格中的店家狀態, e.g. A,C,X,Z
     * @return bool true:上架, false:下架
     */
    function isStoreActivate($status){
        //1: A 一般合約
        //2: B 一般+商城合約
        if((int)$status <= 2){
            return true;
        } else {
            return false;
        }
    }

    /**
     * 建立Stores Table所需初始資料
     * @return stdClass [stores] Table Object
     */
    function CreateStoresObject() {
        $StoreObj = new stdClass();
        $StoreObj->user_id = 1; //admin
        // 創建時間, 更新時間
        $StoreObj->created_at = date("Y-m-d H:i:s");
        $StoreObj->updated_at = date("Y-m-d H:i:s");
        return $StoreObj;
    }

    /**
     * 建立Sales Table所需初始資料
     * @return stdClass [Sales] Table Object
     */
    function CreateSalesObject() {
        $SalesObj = new stdClass();
        // 創建時間, 更新時間
        $SalesObj->created_at = date("Y-m-d H:i:s");
        $SalesObj->updated_at = date("Y-m-d H:i:s");
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
