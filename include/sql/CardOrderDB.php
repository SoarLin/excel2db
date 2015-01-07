<?php
    /**
     * @brief   訂單表單模組
     * Table: card_order
     * 相關操作寫在這邊，從邏輯層呼叫這些操作
     * @date    2014/12/02
     * @version 1.0.0.0
     * @author Soar
     */
    class CardOrderDB extends DBOperate {
        private $dbh;
        /**
         * @brief 建構子
         * @author Soar
         * @param PDO $devDB PDO連線
         */
        function __construct($dbh){
            $this->dbh = $dbh;
            parent::__construct( $this->dbh, 'card_order', 'id');
        }

    }
?>
