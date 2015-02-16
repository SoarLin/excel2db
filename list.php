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
    <form class="row" name="uploadForm" method="post" action="list.php">
      <div class="col-sm-4">
        <input type="text" name="name" class="form-control" placeholder="林○君">
      </div>
      <div class="col-sm-2">
        <button type="submit" class="btn btn-primary btn-block">確認送出</button>
      </div>
    </form>

    <table class="table">
      <thead>
        <tr>
          <th>id</th>
          <th>時間</th>
          <th>姓名</th>
          <th>電話</th>
          <th>Email</th>
          <th>備註</th>
        </tr>
      </thead>
      <tbody>

<?php
    require 'include/config.php';
    require 'include/sql/ListDB.php';
    require 'include/sql/CardOrderDB.php';
    ini_set('date.timezone','Asia/Taipei');

    // $ListDB = new ListDB($devDB);

    $name = @$_POST['name'];
    if (isset($name)){
        $result = checkInputName($name);
        if(!$result) {
            echo "輸入名稱有誤";
        } else {
            $sql = "SELECT * FROM `list` WHERE name LIKE :name";
            $stmt = $devDB->prepare($sql);
            $stmt->bindParam(':name', $result, PDO::PARAM_STR);
            $stmt->execute();
            while($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>".$row['order_id']."</td>";
                echo "<td>".$row['submit']."</td>";
                echo "<td>".$row['name']."</td>";
                echo "<td>".$row['phone']."</td>";
                echo "<td>".$row['email']."</td>";
                echo "<td>".$row['note']."</td>";
                echo "</tr>";
            }

            $sql2 = "SELECT * FROM `card_order` WHERE username LIKE :name AND is_paid = 1";
            $stmt2 = $devDB->prepare($sql2);
            $stmt2->bindParam(':name', $result, PDO::PARAM_STR);
            $stmt2->execute();
            while($row2 = $stmt2->fetch()) {
                echo "<tr class=\"info\">";
                echo "<td>".$row2['id']."</td>";
                echo "<td>付款日 ".$row2['pay_date']."</td>";
                echo "<td>".$row2['username']."</td>";
                echo "<td>".$row2['userphone']."</td>";
                echo "<td>".$row2['useremail']."</td>";
                echo "<td>".$row2['note1']."</td>";
                echo "</tr>";
            }
            unset($row);
            unset($row2);
        }
    }
?>
        <!-- <tr>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
          <td></td>
        </tr> -->
      </tbody>
    </table>

<?php

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

    function getUserByName($name) {
        global $devDB;
        $sql = "SELECT * FROM `list` WHERE name LIKE :name";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch();
        // while($row = $stmt->fetch()) {
        //     echo $row['name'];
        // }
        // if (isset($id)) {
        //     return $id;
        // } else {
        //     return false;
        // }
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

    // 張○○城
    function checkInputName($name) {
        // echo $name."<br>";
        // echo strlen($name)."<br>";
        if(strlen($name) < 6 || strlen($name) > 12) {
            return false;
        }
        // echo strpos($name, "○")."<br>";
        if(strpos($name, "○") != 3) {
            return false;
        }

        $result = str_replace("○", "%", $name);
        return $result;
    }

    closeDevDBConnection();
?>

  </div>
  <script src="components/jquery/dist/jquery.min.js"></script>
  <script src="components/bootstrap/dist/js/bootstrap.min.js"></script>
  <script src="components/jasny-bootstrap/dist/js/jasny-bootstrap.min.js"></script>
  <script src="js/excel2db.js"></script>
</body>
</html>