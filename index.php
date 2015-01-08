<?php
    require_once 'include/config.php';
    require_once 'include/ExcelToMySQL.php';

    define('ShowInfo', false);
    define('SITE_ROOT', realpath(dirname(__FILE__)));

    $excelHandler = new ExcelToMySQL($devDB);

    if(isset($_FILES['userfile'])){
        $excelPath = getUploadFile();
        $excelHandler->setExcelFile($excelPath);
        $excelHandler->handleExcelFile();
    }

function getUploadFile() {
    $uploadfile = basename($_FILES['userfile']['name']);
    $extension_name = substr($uploadfile, stripos($uploadfile, "."));
    // $newfilename = date("YmdHis").$extension_name;
    $newfilename = "test".$extension_name;
    $newfilename_path = SITE_ROOT."/uploads/".$newfilename;
    if(ShowInfo) print_r($newfilename_path);

    if(ShowInfo) echo '<pre>';
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $newfilename_path)) {
        // echo "File is valid, and was successfully uploaded.\n";
        return $newfilename_path;
    } else {
        echo "Possible file upload attack!\n";
        return NULL;
    }

    if(ShowInfo) echo 'Here is some more debugging info:';
    if(ShowInfo) print_r($_FILES);
    if(ShowInfo) print "</pre>";
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