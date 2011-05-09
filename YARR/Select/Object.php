<?php

class YARR_Select_Object extends YARR_Select
{
    protected $class;

    function __construct($class)
    {
        parent::__construct($class::table());
        $this->class = $class;
    }

    public function getOne()
    {
        $row = parent::getOne();
        return $row ? new $this->class($row) : null;
    }

    public function getAll()
    {
        $rows = parent::getAll();

        $ret = array();
        foreach ($rows as $row) {
            $ret[] = new $this->class($row);
        }

        return $ret;
    }

    public function current()
    {
        return $this->iterator_cur ? new $this->class($this->iterator_cur) : null;
    }
}
