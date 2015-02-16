<?php
    require 'include/config.php';
    require 'include/sql/ListDB.php';
    require 'include/sql/MissListDB.php';
    require 'include/sql/CardOrderDB.php';

    $CardDB = new CardOrderDB($devDB);
    $MissDB = new MissListDB($devDB);
    $Mode = "UpdateCardOrder";
    // $Mode = "UpdateMissList";
    $updateCount = 0;
    $insertCount = 0;
    $findPayCount = 0;
    $notMatch = 0;
    $matchOne = 0;
    $matchMulti = 0;
    $missArray = array();

    $sql = "SELECT id, pay_date,card_user FROM `card_order` WHERE zipcode is NULL order by `pay_date`";
    $stmt = $devDB->prepare($sql);
    // $stmt->bindParam(':eatme_no', $nomber, PDO::PARAM_STR);
    $stmt->execute();
    while($row1 = $stmt->fetch()) {
        // echo $row['pay_date'].", ".$row['card_user']."<br>";

        $id         = $row1['id'];
        $fuzzy_name = $row1['card_user'];
        $pay_date   = $row1['pay_date'];

        $find_name = str_replace("?", "", $fuzzy_name);
        $find_name = str_replace("○", "%", $find_name);

        $sql2 = "SELECT * FROM `list` WHERE name LIKE :name";
        $stmt2 = $devDB->prepare($sql2);
        $stmt2->bindParam(':name', $find_name, PDO::PARAM_STR);
        $stmt2->execute();
        $count = $stmt2->rowCount();
        if($Mode == "UpdateCardOrder") {
            if ($count == 0){
                $notMatch++;
                array_push($missArray, $fuzzy_name);
            } else if ($count == 1){
                // echo $fuzzy_name."<br>";
                $matchOne++;
                $row2 = $stmt2->fetch();
                $UpdateObj = new stdClass;
                $UpdateObj->username  = $row2['name'];
                $UpdateObj->userphone = $row2['phone'];
                $UpdateObj->useremail = $row2['email'];
                $UpdateObj->user_no  = $row2['name'];
                $CardDB->update($id, $UpdateObj);
            } else if ($count > 1){
                $matchMulti++;
            }
        } else if($Mode == "UpdateMissList") {
            while($row2 = $stmt2->fetch()) {
                $MissObj = new stdClass();
                $MissObj->name = $row2['name'];
                $MissObj->email = $row2['email'];
                $MissObj->phone = $row2['phone'];
                $MissObj->submit = $row2['submit'];
                $MissObj->fuzzy_name = $fuzzy_name;
                $MissObj->pay_date = $pay_date;
                $mid = null;
                if ($mid = $MissDB->getIdByData($MissObj)){
                    $MissDB->update($mid, $MissObj);
                    $updateCount++;
                } else {
                    $mid = $MissDB->insert($MissObj);
                    $insertCount++;
                }

                if ($note = findInCardOrder($MissObj->phone)){
                    $MissObj->note = $note;
                    $MissDB->update($mid, $MissObj);
                    $findPayCount++;
                }
            }
        }
    }

    if($Mode == "UpdateCardOrder") {
        echo "找不到名字的有".$notMatch."筆<br>";
        var_dump($missArray);
        echo "找到一個名字，有".$matchOne."筆<br>";
        echo "找到多個名字，有".$matchMulti."筆<br>";
        echo "交易成功總共有".($notMatch+$matchOne+$matchMulti)."筆<br>";
    } else if($Mode == "UpdateMissList") {
        echo "總共更新".$updateCount."筆<br>";
        echo "總共新增".$insertCount."筆<br>";
        echo "找到有付款的".$findPayCount."筆<br>";
    }


    closeDevDBConnection();

    function findInCardOrder($phone) {
        global $devDB;
        $sql = "SELECT * FROM `card_order` WHERE userphone = :phone AND is_paid = 1 AND zipcode is not null";
        $stmt = $devDB->prepare($sql);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
        $stmt->execute();
        while($row = $stmt->fetch()) {
            $pay_date = $row['pay_date'];
            $note1    = $row['note1'];
            return "付款日:".$pay_date.", ".$note1;
        }
        return false;
    }
?>