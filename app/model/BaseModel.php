<?php

namespace App\Model;

use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\InvalidStateException;
use Nette\Object;
use Nette\Utils\Strings;
use Traversable;

abstract class BaseModel extends Object
{
    private static $activeTransactionIterator = 0;

    /** @var Context */
    protected $database;

    /** @var string */
    protected $table;

    /** @var array */
    protected $listData = array();

    /**
     * Constructor
     *
     * @param Context $database
     */
    function __construct(Context $database) {
        $this->setDatabase($database);
    }


    /**
     * Table rows count getter
     *
     * @return integer
     */

    public function count()
    {
        return $this->getTable()->count();
    }

    /**
     * Return item by primary key
     *
     * @param integer $key
     * @return ActiveRow
     */
    public function get($key)
    {
        return $this->getTable()->get($key);
    }

    public function group($key){
        return $this->getTable()->group($key);
    }

    /**
     * Alias of <b>getTable</b>
     *
     * @return Selection
     */
    public function getAll()
    {
        return $this->getTable();
    }

    /**
     * @return bool|mixed|IRow
     */
    public function fetch(){
        return $this->getTable()->fetch();
    }
    /**
     * Vrací vyfiltrované záznamy na základě vstupního pole
     * (pole array('name' => 'David') se převede na část SQL dotazu WHERE name = 'David')
     * @param string $key
     * @param string $val
     * @return \Nette\Database\Table\Selection
     */
    public function fetchPairs($key=null,$val=null){
        return $this->getTable()->fetchPairs($key,$val);

    }

    public function fetchAssoc($path){
        return $this->getTable()->fetchAssoc($path);
    }

    public function findBy($by) {
        return $this->getTable()->where($by);
    }

    public function findOneBy($by) {
        return $this->getTable()->where($by)->fetch();
    }

    public function query($sql){
        return $this->getDatabase()->query(...func_get_args());
    }

    /** @return void */
    public function beginTransaction()
    {
        if (++self::$activeTransactionIterator == 1) {
            $this->getDatabase()->beginTransaction();
        }
    }

    /** @return void */
    public function commit()
    {
        if (--self::$activeTransactionIterator == 0) {
            $this->getDatabase()->commit();
        }
    }

    /** @return void */
    public function rollBack()
    {
        if (--self::$activeTransactionIterator == 0) {
            $this->getDatabase()->rollBack();
        }
    }
    /**
     * Table getter
     *
     * @param null $tableName
     *
     * @return Selection
     */
    public function getTable($tableName = null)
    {
        if (!is_null($tableName)) return $this->getDatabase()->table($tableName);
        else return $this->getDatabase()->table($this->getTableName());
    }

    /**
     * Inserts row in a table.
     *
     * @param  array|Traversable|Selection array($column => $value)|\Traversable|Selection for INSERT ... SELECT
     * @return IRow|int|bool Returns IRow or number of affected rows for Selection or table without primary key
     */
    public function insert($data)
    {
        return $this->getTable()->insert($data);
    }

    public function delete(){
        return $this->getTable()->delete();
    }
    /**
     * Adds select clause, more calls appends to the end.
     * @param  string for example "column, MD5(column) AS column_md5"
     * @return Selection
     */
    public function select($columns)
    {
        return $this->getTable()->select(...func_get_args());
    }

    /**
     * Sets limit clause, more calls rewrite old values.
     *
     * @param integer
     * @param integer [OPTIONAL]
     * @return Selection
     */
    public function limit($limit, $offset = NULL)
    {
        return $this->getTable()->limit($limit, $offset);
    }

    /**
     * Zkratka pro where
     *
     * @param string $order
     * @return Selection
     */
    public function order($order)
    {
        return $this->getTable()->order(...func_get_args());
    }

    public function groupBy($group)
    {
        return $this->getTable()->group(...func_get_args());
    }
    /**
     * Update data in database
     *
     * @param array $data
     * @return Selection
     */
    public function update($data)
    {
        return $this->getTable()->update($data);
    }

    /**
     * Search for row in the table
     *
     * @param string $condition
     * @param array $parameters
     * @return Selection
     */
    public function where($condition, $parameters = array())
    {
        return $this->getTable()->where(...func_get_args());
    }

    /**
     * Database getter
     *
     * @return Context
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Database setter
     *
     * @param Context $database
     * @return BaseModel Provides fluent interface
     * @throws InvalidStateException
     */
    public function setDatabase(Context $database)
    {
        if ($this->database !== NULL) {
            throw new InvalidStateException('Database has already been set');
        }
        $this->database = $database;
        return $this;

    }

    /**
     * Table name getter
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->table;
    }

    public function aggregation($function)
    {
        return $this->getTable()->aggregation($function);
    }
    // </editor-fold>

    /**
     * Load list data id,name
     * @return $this
     */
    private function loadListData()
    {
        if (!count($this->listData)) {
            try {
                $data = $this->query("SELECT id,name FROM " . $this->getTableName())->fetchAll();
                foreach ($data as $row) {
                    $this->listData['fetchAll'][]=$row;
                    $this->listData['byId'][$row['id']]=$row['name'];
                    $this->listData['byName'][$row['name']]=$row['id'];
                }
            } catch (\Exception $e) {

            }
        }
        return $this;
    }

    /**
     * Get ID by Name
     * @param int $name
     * @return int
     */
    public function getIdByName($name)
    {
        if (!count($this->listData)) $this->loadListData();
        if (isset($this->listData['byName'][$name])) {
            return $this->listData['byName'][$name];
        } else {
            return null;
        }
    }

    /**
     * Get Name by Name
     * @param int $id
     * @return string
     */
    public function getNameById($id)
    {
        if (!count($this->listData)) $this->loadListData();
        if (isset($this->listData['byId'][$id])) {
            return $this->listData['byId'][$id];
        } else {
            return null;
        }
    }

    /**
     * Get list Data, id, name
     * @return array
     */
    public function fetchListData()
    {
        if (!count($this->listData)) $this->loadListData();
        if (isset($this->listData['fetchAll'])) {
            return $this->listData['fetchAll'];
        } else {
            return null;
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    private function sanitizeName($name)
    {
        $name = Strings::webalize($name);
        return str_replace('-', '', $name);
    }
}