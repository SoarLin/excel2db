<?php
    /**
     * @brief   餐廳-餐點種類 關聯表
     * Table: store_category
     * 相關操作寫在這邊，從邏輯層呼叫這些操作
     * @date    2015/01/14
     * @version 1.0.0.0
     * @author Soar
     */
    class StoreCategoryDB extends DBOperate {
        private $dbh;
        /**
         * @brief 建構子
         * @author Soar
         * @param PDO $devDB PDO連線
         */
        function __construct($dbh){
            $this->dbh = $dbh;
            parent::__construct( $this->dbh, 'store_category', 'id');
        }

    }
?>
