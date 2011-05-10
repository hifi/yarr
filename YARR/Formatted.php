<?php

class YARR_Formatted extends YARR
{
    protected $_format;

    function __construct($data)
    {
        parent::__construct($data);
        $this->_format = new YARR_Format($this);
    }

    function __get($k)
    {
        if ($this->_format->supported($k)) {
            return $this->_format->type($k);
        }

        return parent::__get($k);
    }

    function __set($k, $v)
    {
        if ($this->_format->supported($k)) {
            return $this->_format->type($k);
        }

        return parent::__set($k, $v);
    }
}
