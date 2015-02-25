<?php
    /**
     * @brief   店家代碼表單模組
     * Table: store_num
     * 相關操作寫在這邊，從邏輯層呼叫這些操作
     * @date    2014/02/16
     * @version 1.0.0.0
     * @author Soar
     */
    class StoreNumDB extends DBOperate {
        private $dbh;
        /**
         * @brief 建構子
         * @author Soar
         * @param PDO $devDB PDO連線
         */
        function __construct($dbh){
            $this->dbh = $dbh;
            parent::__construct( $this->dbh, 'store_num', 'id');
        }

    }
?>
