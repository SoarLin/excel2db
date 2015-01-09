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
        $Sobj = $this->CreateStoresObject();
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
            echo "讀取最高行數 = ".$highestRow.", 分頁名稱 = ".$sheetName."<br/>";

            for($j = $rowIndex ; $j <= $highestRow; $j++){
                echo "第".$j."行資料";
                $this->getStoreBasicInfo($Sobj, $activeSheet, $j);
                $this->getStoreAddress($Sobj, $activeSheet, $j);
                var_dump($Sobj);
            }
        }
    }


    function getStoreAdditional(&$Sobj, $activeSheet, $j) {
        // 營業時間先不處理, 工讀生手動上資料
        $Sobj->operate_time = "";
        // 公休日
        $Sobj->rest_time = trim( $activeSheet->getCell("AH"."$j")->getValue() );
        // 付款方式, 預設現金
        $Sobj->pay_way = "cash";
        // 客均價先不處理, 工讀生手動上資料
        $Sobj->price = "";
        // 
    }

    /**
     * 讀取Excel中店家縣市區域資料
     * @param &stdClass 店家物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param rowIndex 讀取資料起始行數
     * @param highestRow 讀取資料結束行數
     * @return none
     */
    function getStoreAddress(&$Sobj, $activeSheet, $j) {
        // 店家代碼
        $store_code = trim( $activeSheet->getCell("B"."$j")->getValue() );
        $zipcode = substr($store_code, 2, 3);
        $this->setCityArea($Sobj, $zipcode);

        // 地址暫時不處理，請工讀生日後手動填入
        $Sobj->address = trim( $activeSheet->getCell("J"."$j")->getValue() );
    }

    /**
     * 讀取表格中店家基本資料
     * @param &stdClass 店家物件參考位址
     * @param activeSheet Excel中正在讀取的分頁
     * @param rowIndex 讀取資料起始行數
     * @param highestRow 讀取資料結束行數
     * @return none
     */
    function getStoreBasicInfo(&$Sobj, $activeSheet, $j) {
        // 店名
        $Sobj->name     = trim( $activeSheet->getCell("E"."$j")->getValue() );
        // 分店名
        $Sobj->branch   = trim( $activeSheet->getCell("G"."$j")->getValue() );
        // 電話
        $Sobj->tel      = trim( $activeSheet->getCell("M"."$j")->getValue() );
        // 傳真, 目前合約沒這資料
        $Sobj->fax      = "";
        // email
        $Sobj->email    = trim( $activeSheet->getCell("V"."$j")->getValue() );
        // 網站
        $Sobj->website  = trim( $activeSheet->getCell("L"."$j")->getValue() );
        // FB
        $Sobj->facebook = trim( $activeSheet->getCell("P"."$j")->getValue() );
    }


    /**
     * 取得縣市id與區域id
     * @param &stdClass 店家物件參考位址
     * @param zipcode 郵遞區號
     * @return none
     */
    function setCityArea(&$Sobj, $zipcode) {
        $sql = "SELECT * FROM `area_data` WHERE zipcode = :zipcode";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':zipcode', $zipcode, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $Sobj->city_id = $row['city_id'];
            $Sobj->area_id = $row['id'];
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

    /**
     * 建立Stores Table所需初始資料
     * @return stdClass [stores] Table Object
     */
    function CreateStoresObject() {
        $Sobj = new stdClass();
        $Sobj->user_id = 1; //admin
        $Sobj->pending = 'pending'; //預設下架狀態
        $Sobj->created_at = time();
        $Sobj->updated_at = time();
        return $Sobj;
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