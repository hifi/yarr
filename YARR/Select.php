<?php

class YARR_Select extends Zend_Db_Select implements Countable, Iterator
{
    protected $db;
    protected $iterator_stmt;
    protected $iterator_pos;
    protected $iterator_cur;

    function __construct($table)
    {
        $this->db = YARR::getDb();
        parent::__construct($this->db);
        $this->from($table);
    }

    public function getOne()
    {
        $row = $this->db->fetchRow($this);
        return $row ? $row : null;
    }

    public function getAll()
    {
        return $this->db->fetchAll($this);
    }

    public function count()
    {
        $select = clone $this;
        return $this->db->fetchOne($select->reset('columns')->columns('COUNT(*)'));
    }

    public function current()
    {
        return $this->iterator_cur;
    }

    public function key()
    {
        return $this->iterator_pos;
    }

    public function next()
    {
        $this->iterator_cur = $this->iterator_stmt->fetch();
        $this->iterator_pos++;
    }

    public function rewind()
    {
        $this->iterator_stmt = $this->db->query($this);
        $this->iterator_pos = 0;
        $this->iterator_cur = $this->iterator_stmt->fetch();
    }

    public function valid()
    {
        return ($this->iterator_cur !== false);
    }
}
