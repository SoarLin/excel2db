<?php
    global $devDB;

    function getDevDBConnection(){
        global $devDB;
        $db_type = 'mysql';
        $db_host = 'eatmedb.cgcvau6esuvd.ap-northeast-1.rds.amazonaws.com';
        $db_name = 'cashflow';
        $db_user = 'eatmedev';
        $db_pass = 'eatmedev';
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