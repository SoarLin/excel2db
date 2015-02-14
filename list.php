<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta name="description" content="">
  <meta name="keywords" content="">
  <meta name="author" content="Soar">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <!-- <link rel="icon" href="images/favicons/favicon.ico?v=1"> -->
  <title>店家Excel檔匯入資料庫</title>

  <link rel="stylesheet" href="components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="components/jasny-bootstrap/dist/css/jasny-bootstrap.min.css">

  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
          <span class="sr-only">導航切換</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="#">Excel 匯入</a>
      </div>
      <div id="navbar" class="navbar-collapse collapse">
        <ul class="nav navbar-nav navbar-right">
          <li><a href="#">關於食我</a></li>
          <li><a href="#">聯絡我們</a></li>
        </ul>
      </div><!--/.navbar-collapse -->
    </div>
  </nav>

  <div class="container main-field">
    <form class="row" name="uploadForm" method="post" action="index.php" enctype="multipart/form-data">
      <div class="col-md-10">

      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-block">確認送出</button>
      </div>
    </form>

  </div>

<?php
    require 'include/config.php';
    require 'include/sql/ListDB.php';
    include 'include/Classes/PHPExcel.php';
    ini_set('date.timezone','Asia/Taipei');

    // $PHPExcel = null;
    // $insertCount = 0;
    // $updateCount = 0;
    // $ListDB = new ListDB($devDB);

    // $file = "Preorder20150212.xlsx";
    // try {
    //     $PHPExcel = PHPExcel_IOFactory::load($file);
    // } catch(Exception $e) {
    //     die('Error loading file "'.pathinfo($file,PATHINFO_BASENAME).'": '.$e->getMessage());
    // }
    // //讀取工作表分頁數
    // $sheetCount = $PHPExcel->getSheetCount();
    // //讀取第一個分頁資料, 分頁名稱
    // $activeSheet = $PHPExcel->getSheet(0);
    // $sheetName = $activeSheet->getTitle();

    // $highestRow = $activeSheet->getHighestRow();
    // echo "<p>分頁名稱 [".$sheetName."], 最高行數 = ".$highestRow."</p>";

    // $rowIndex = 2;
    // // $highestRow = 10;
    // for($j = $rowIndex ; $j <= $highestRow; $j++){
    //     $ListObj = new stdClass();
    //     $ListObj->order_id = trim($activeSheet->getCell("A"."$j")->getValue());
    //     $ListObj->submit = setMySQLDATETIME($activeSheet->getCell("C"."$j")->getValue());
    //     $ListObj->name = trim($activeSheet->getCell("D"."$j")->getValue());
    //     $ListObj->email = trim($activeSheet->getCell("E"."$j")->getValue());
    //     $ListObj->phone = trim($activeSheet->getCell("F"."$j")->getValue());
    //     $ListObj->note = trim($activeSheet->getCell("G"."$j")->getValue());
    //     // var_dump($ListObj);


    //     if( $id = getListId($ListObj->email) ){
    //         // update
    //         $ListDB->update($id, $ListObj);
    //         $updateCount++;
    //     } else {
    //         // insert
    //         $id = $ListDB->insert($ListObj);
    //         $insertCount++;
    //     }
    // }

    // echo "新增資料筆數 : ".$insertCount."<br>";
    // echo "更新資料筆數 : ".$updateCount."<br>";

    function getListId($email){
        global $devDB;
        $sql = "SELECT * FROM `list` WHERE email = :email";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
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


  <script src="components/jquery/dist/jquery.min.js"></script>
  <script src="components/bootstrap/dist/js/bootstrap.min.js"></script>
  <script src="components/jasny-bootstrap/dist/js/jasny-bootstrap.min.js"></script>
  <script src="js/excel2db.js"></script>
</body>
</html>