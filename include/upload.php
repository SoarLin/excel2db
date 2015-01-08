<?php
    require_once 'config.php';

    define('ShowInfo', true);
    define('SITE_ROOT', realpath(dirname(__FILE__)));

    if(isset($_FILES['userfile'])){
        $excel_file = getUploadFile();
    }

function getUploadFile() {
    $uploadfile = basename($_FILES['userfile']['name']);
    $extension_name = substr($uploadfile, stripos($uploadfile, "."));
    $newfilename = date("YmdHis").$extension_name;
    $newfilename_path = SITE_ROOT."/../uploads/".$newfilename;
    if(ShowInfo) print_r($newfilename_path);

    if(ShowInfo) echo '<pre>';
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $newfilename_path)) {
        // echo "File is valid, and was successfully uploaded.\n";
        return $newfilename_path;
    } else {
        // echo "Possible file upload attack!\n";
        return NULL;
    }

    if(ShowInfo) echo 'Here is some more debugging info:';
    if(ShowInfo) print_r($_FILES);
    if(ShowInfo) print "</pre>";
}
?>