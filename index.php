<?php
    require_once 'include/config.php';
    require_once 'include/ExcelToMySQL.php';
    ini_set('date.timezone','Asia/Taipei');

    define('ShowInfo', false);
    define('SITE_ROOT', realpath(dirname(__FILE__)));

    $excelHandler = new ExcelToMySQL($devDB);

    if(isset($_FILES['userfile'])){
        $start = microtime(true);

        $excelPath = getUploadFile();
        $extension_name = pathinfo($excelPath,PATHINFO_EXTENSION);

        echo "上傳一份Excel資料，花費時間 = ".(microtime(true) - $start)."<br>";

        if ($extension_name == "xlsx" || $extension_name == "xls") {
            $read_start = microtime(true);
            $excelHandler->setExcelFile($excelPath);
            $time_elapsed_us = microtime(true) - $read_start;
            echo "讀取一份Excel資料，花費時間 = ".$time_elapsed_us."<br>";

            $handle_start = microtime(true);
            $excelHandler->handleExcelFile();
            $time_elapsed_us = microtime(true) - $handle_start;
            echo "處理一份Excel資料，花費時間 = ".$time_elapsed_us."<br>";
        } else {
            echo "<div class=\"my-alert text-center\"><div class=\"alert alert-danger alert-dismissible\" role=\"alert\">".
            "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">".
            "<span aria-hidden=\"true\">&times;</span></button><strong>錯誤! </strong> 請上傳檔案Excel檔案</div></div>";
        }
        $time_elapsed_us = microtime(true) - $start;
        echo "處理這份Excel資料，總共花費時間 = ".$time_elapsed_us."<br>";
    }

    // $date = "03-30-13";
    // echo "時間 : ".$date.", timestamp = ";
    // list($month, $day, $year) = explode('-', $date);
    // echo mktime(0, 0, 0, $month, $day, $year), "<br>";
    // echo strtotime($date), "<br>";
    // $date = "2013-03-30";
    // echo "時間 : ".$date.", timestamp = ";
    // echo strtotime($date), "<br>";
    // $date = "2012/10/25";
    // echo "時間 : ".$date.", timestamp = ";
    // echo date("Y-m-d H:i:s", strtotime($date)), "<br>";
    // $date = "";
    // echo var_dump(is_numeric($date));
    // $date = "41363";
    // echo var_dump(is_numeric($date));

    // $temp = "歐式料理, 素食, 早午餐";
    // $tempAry = explode(',', $temp);
    // $cataAry = array();
    // foreach ($tempAry as $key => $value) {
    //     echo "[".$key.":".trim($value)."],";
    // }

function getUploadFile() {
    $uploadfile = basename($_FILES['userfile']['name']);
    $extension = pathinfo($uploadfile,PATHINFO_EXTENSION);
    // $newfilename = date("Y-m-d_H:i:s").".".$extension;
    $newfilename = "test".".".$extension;
    $newfilename_path = SITE_ROOT."/uploads/".$newfilename;
    if(ShowInfo) echo $newfilename_path;

    if(ShowInfo) echo '<pre>';
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $newfilename_path)) {
        if(ShowInfo) echo "File is valid, and was successfully uploaded.\n";
    } else {
        if(ShowInfo) echo "Possible file upload attack!\n";
        $newfilename_path = "";
    }

    if(ShowInfo) echo 'Here is some more debugging info:';
    if(ShowInfo) print_r($_FILES);
    if(ShowInfo) echo "</pre>";
    return $newfilename_path;
}

?>
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
        <div class="fileinput fileinput-new input-group" data-provides="fileinput" data-name="userfile">
          <div class="form-control" data-trigger="fileinput">
            <i class="glyphicon glyphicon-file fileinput-exists"></i>
            <span class="fileinput-filename"></span>
          </div>
          <span class="input-group-addon btn btn-default btn-file">
            <span class="fileinput-new">Select file</span>
            <span class="fileinput-exists">Change</span>
            <input type="file" name="userfile">
          </span>
          <a href="#" class="input-group-addon btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a>
        </div>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-block">確認上傳</button>
      </div>
    </form>

  </div>


  <script src="components/jquery/dist/jquery.min.js"></script>
  <script src="components/bootstrap/dist/js/bootstrap.min.js"></script>
  <script src="components/jasny-bootstrap/dist/js/jasny-bootstrap.min.js"></script>
  <script src="js/excel2db.js"></script>
</body>
</html>