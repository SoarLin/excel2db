<?php
    /**
     * @brief   表單操作基本模組
     * Table:
     * 相關操作寫在這邊，各個表單從這裡繼承，各自覆寫或添加所需功能
     * @date    2014/12/02
     * @version 1.0.0.0
     * @author Soar
     */
    include_once('IDBOperate.php');
    class DBOperate implements IDBOperate
    {
        private $dbh;
        private $table = '';
        private $idName;
        /**
         * @brief 建構子
         * @author Soar
         * @param PDO $dbh PDO連線
         */
        function __construct($dbh, $tb_name, $id_name){
            if(is_null($dbh))throw new Exception( '資料庫連結沒設' );
            $this->dbh = $dbh;
            if(is_null($tb_name))throw new Exception( '第一個參數 tableName 一定要設' );
            $this->table = $tb_name;
            $this->idName = $id_name;
        }

        /**
         * @brief 新增
         * @param object $obj 要新增的使用者物件
         * @return 結果碼
         * @see IDBOperate::insert()
         */
        function insert($obj){
            try{
                $sqla = "INSERT INTO `". $this->table ."` (";
                $sqlb = ") VALUES (";
                $args = array();
                foreach($obj as $key => $value){
                    $sqla .= "`". $key ."`,";
                    $sqlb .= "?,";
                    array_push($args, $value);
                }
                $sqla = substr($sqla, 0, strlen($sqla)-1);
                $sqlb = substr($sqlb, 0, strlen($sqlb)-1). ");";
                // echo $sqla.$sqlb;
                // foreach($args as $v){
                //     echo $v.", ";
                // }
                $sth = $this->dbh->prepare($sqla.$sqlb);
                $count = $sth->execute($args);
                if($count > 0){
                    return $this->getLastID();
                } else {
                    return $count;
                }
            } catch (PDOException $e){
                throw $e;
            }
        }

        /**
         * @brief 修改
         * @param int $id 要被修改的ID
         * @param object $obj 要修改的資料
         * @return 結果碼
         * @see IDBOperate::update()
         */
        function update($id, $obj){
            try{
                $sqla = "UPDATE `". $this->table ."` SET ";
                $args = array();
                foreach($obj as $key => $value){
                    $sqla .= "`". $key ."` = ?,";
                    array_push($args, $value);
                }
                $sqla = substr($sqla, 0, strlen($sqla)-1). " WHERE `" . $this->idName . "` = ?";
                array_push($args, $id);
                // echo $sqla."<br>";
                // foreach($args as $v){
                //     echo $v.", ";
                // }
                $sth = $this->dbh->prepare($sqla);
                $sth->execute($args);
                return $sth->rowCount();
            } catch (PDOException $e){
                throw $e;
            }
        }

        /**
         * @brief 刪除
         * @param int $id 要被刪除的ID
         * @return 結果碼
         * @see IDBOperate::delete()
         */
        function delete($id){
            try{
                $sql = "DELETE FROM `". $this->table ."` WHERE `" . $this->idName . "` = :id;";
                $args = array(':id' => $id);
                $sth = $this->dbh->prepare($sql);
                return $sth->execute($args);
            } catch (PDOException $e){
                throw $e;
            }
        }

        /**
         * @brief 依流水號取得特定USER所有資料
         * @param int $id 使用者ID
         * @return 結果陣列
         * @see IDBOperate::getById()
         */
        function getById($id){
            $sql = "SELECT * FROM `". $this->table ."` WHERE `" . $this->idName . "` = :id;";
            $args = array(':id' => $id);
            $sth = $this->dbh->prepare($sql);
            $sth->execute($args);
            $sth->setFetchMode(PDO::FETCH_ASSOC);
            return $sth->fetchAll();
        }

        /**
         * @brief 取得所有使用者
         * @return 結果陣列
         * @see IDBOperate::getAll()
         */
        function getAll(){
            $sql = "SELECT * FROM `". $this->table ."` ORDER BY `" . $this->idName . "` ASC";
            $sth = $this->dbh->prepare($sql);
            $sth->execute();
            return $sth->fetchAll();
        }

        /**
         * @brief 回傳最後一筆新增的使用者ID
         */
        function getLastID(){
            return $this->dbh->lastInsertId("`".$this->idName."`");
        }

    }
