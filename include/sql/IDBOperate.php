<?php
    /**
     * @brief   所有資料庫存取都要實作這個介面
     * @version 1.0.0.0
     * @date    2014/12/02
     * @author Soar
     */
    interface IDBOperate {
        /**
         * @brief 新增
         * @param object $obj 並把要的值依table塞進去
         * @return 結果碼
         */
        function insert($obj);

        /**
         * @brief 刪除(視情況是否真實刪除資料)
         * @param int $id 要刪除的流水號
         * @return 結果碼
         */
        function delete($id);

        /**
         * @brief 修改
         * @param int $id 要修改的流水號
         * @param object $obj 要更新的值(塞進stdClass())
         * @return 結果碼
         */
        function update($id, $obj);

        /**
         * @brief 取得所有資料
         * @return 結果陣列
         */
        function getAll();

        /**
         * @brief 取得指定資料
         * @param int $id 流水號
         * @return 結果陣列
         */
        function getById($id);
    }
?>
