<?php
    require 'include/config.php';
    require 'include/sql/WishListDB.php';

    $WishDB = new WishListDB($devDB);
    $Mode = "NotifyMode";
    // $Mode = 'WishMode';
    $updateCount = 0;
    $insertCount = 0;


    $json = file_get_contents("notify_0319-0326.json");
    $json_arary = json_decode($json);

    $email = '';
    if ($Mode == 'WishMode') {
        $name = '';
    }

    foreach ($json_arary as $i => $wish) {
        $email = $wish->email;
        if ($Mode == 'WishMode') {
            $name  = $wish->name;
            echo $name.", ";
        }
        // echo $email."<br>";
        // echo $wish->name."<br>";
        $sql = "SELECT * FROM `wish_list` WHERE email = :eamil";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':eamil', $wish->email, PDO::PARAM_STR);
        $stmt->execute();
        $id = '';
        $count = 0;
        while($row1 = $stmt->fetch()) {
            $id    = $row1['id'];
            $count = $row1['count'];
        }
        unset($sql);
        unset($stmt);

        if ($id != '') {
            // 一樣email
            $WLObj = new stdClass;
            $WLObj->count = (int)$count + 1;
            $WishDB->update($id, $WLObj);
            $updateCount++;
        } else {
            // 沒有email
            $WLObj = new stdClass;
            if ($Mode == 'WishMode') {
                $WLObj->name = $name;
            }
            $WLObj->email = $email;
            $WLObj->count = 0;
            $WishDB->insert($WLObj);
            $insertCount++;
        }
    }

    echo "更新次數：".$updateCount."<br>";
    echo "新增次數：".$insertCount."<br>";


    closeDevDBConnection();

?>