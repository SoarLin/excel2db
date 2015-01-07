<?php
    define("DEBUG", false);

    require_once 'sql/DBOperate.php';
    require_once 'dbconfig_dev.php';

    $devDB = getDevDBConnection();

    if(DEBUG) {
        $devDB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }

?>
