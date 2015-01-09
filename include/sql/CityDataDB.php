<?php
    /**
     * @brief   所屬縣市表格
     * Table: city_data
     * 相關操作寫在這邊，從邏輯層呼叫這些操作
     * @date    2015/01/08
     * @version 1.0.0.0
     * @author Soar
     */
    class CityDataDB extends DBOperate {
        private $dbh;
        /**
         * @brief 建構子
         * @author Soar
         * @param PDO $devDB PDO連線
         */
        function __construct($dbh){
            $this->dbh = $dbh;
            parent::__construct( $this->dbh, 'city_data', 'id');
        }

    }
?>
