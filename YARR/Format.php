<?php

class YARR_Format
{
    protected $_obj;
    protected $_type = 'html';

    function __construct($obj)
    {
        $this->_obj = $obj;
    }

    function supported($type)
    {
        if ($type == 'html')
            return true;

        return false;
    }

    function type($type)
    {
        $this->_type = $type;
        return $this;
    }

    static function to($type, $value)
    {
        if ($type == 'html') {
            return htmlspecialchars($value);
        }

        return $value;
    }

    static function from($type, $value)
    {
        if ($type == 'html') {
            return htmlspecialchars_decode($value);
        }

        return $value;
    }

    function __get($key)
    {
        return self::to($this->_type, $this->_obj->$key);
    }

    function __set($key, $value)
    {
        return ($this->_obj->$key = self::from($this->_type, $value));
    }
}
