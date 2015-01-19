<?php
    global $devDB;

    function getDevDBConnection(){
        global $devDB;
        $server_url = $_SERVER['SERVER_NAME'];
        if ($server_url == "soar.eatme.tw") {
            $db_host = 'eatmedb.cgcvau6esuvd.ap-northeast-1.rds.amazonaws.com';
            $db_name = 'eatme_app_dev';
            $db_user = 'eatmedev';
            $db_pass = 'eatmedev';
        } else if ($server_url == "localhost") {
            $db_host = '127.0.0.1';
            $db_name = 'test0401';
            $db_user = 'root';
            $db_pass = 'mysql';
        }
        $db_type = 'mysql';
        $connect_host = $db_type . ':host=' . $db_host . ';dbname=' . $db_name;

        try{
            if(!$devDB){
                $devDB = new PDO($connect_host, $db_user, $db_pass);
                $devDB->query('SET NAMES UTF8');
            }
            return $devDB;
        } catch (PDOException $e) {
            echo 'Error!: '.$e->getMessage() . '<br />';
            die();
        }
    }

    function closeDevDBConnection(){
        try {
            global $devDB;
            $devDB = null;
        }catch(Exception $e){
            $devDB = null;
            die();
        }
    }

?>
