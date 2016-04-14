<?php
    /**
     * @brief temp
     * Table: temp
     * 相關操作寫在這邊，從邏輯層呼叫這些操作
     * @date    2015/12/24
     * @version 1.0.0.0
     * @author Soar
     */
    class TempDB extends DBOperate {
        private $dbh;
        /**
         * @brief 建構子
         * @author Soar
         * @param PDO $devDB PDO連線
         */
        function __construct($dbh){
            $this->dbh = $dbh;
            parent::__construct( $this->dbh, 'technical_report_copy', 'id');
        }

    }
?>
