<?php

class YARR
{
    const table = false;

    protected static $fields = false;
    private static $db = false;

    protected static $has_one = array();
    protected static $has_many = array();
    protected static $belongs_to = array();

    protected $_data;
    protected $_dirty;

    protected static $_true = true;
    protected static $_false = false;
    protected static $_null = null;

    static public function setDb(Zend_Db_Adapter_Abstract $db)
    {
        self::$db = $db;
    }

    static public function getDb()
    {
        return self::$db;
    }

    static public function table()
    {
        $class = get_called_class();
        $table = constant($class.'::table');

        if (!$table) {
            return strtolower($class);
        }

        return $table;
    }

    static public function fields()
    {
        if (!static::$fields) {
            static::$fields = self::$db->describeTable(static::table());
        }

        return static::$fields;
    }

    function __construct($data)
    {
        $this->_data = array();

        /* handle defaults */
        foreach (static::fields() as $k => $desc) {
            $default = $desc['DEFAULT'];
            if ($default == 'NULL') {
                $default = NULL;
            }
            else if (preg_match("/^'(.*)'$/", $default, $m)) {
                $default = $m[1];
            }
            $this->_data[$k] = $default;
        }

        if (!is_array($data)) {
            debug_print_backtrace();
            exit;
        }
        $this->_data = array_replace($this->_data, $data);
        $this->_dirty = array();
    }

    static public function get($id)
    {
        return static::select()->where('id = ?', $id)->getOne();
    }

    static public function create($data = array())
    {
        $class = get_called_class();
        return new $class($data);
    }

    static public function select()
    {
        return new YARR_Select_Object(get_called_class());
    }

    static public function selectArray()
    {
        $class = get_called_class();
        return new YARR_Select($class::table());
    }

    function &__get($k)
    {
        if (array_key_exists($k, $this->_data)) {
            return $this->_data[$k];
        }

        return self::$_null;
    }

    function __set($k, $v)
    {
        if (array_key_exists($k, $this->_data)) {
            $this->_dirty[$k] = true;
            return ($this->_data[$k] = $v);
        }

        return false;
    }

    function &__call($name, $args)
    {
        if (array_key_exists($name, static::$has_many)) {
            $desc = static::$has_many[$name];
            $key = isset($desc['key']) ? $desc['key'] : self::table().'_id';

            $select = call_user_func_array(array($desc['class'], 'select'), array());
            $db = $select->getAdapter();
            $select->where($db->quoteIdentifier($key).' = ?', $this->_data['id']);

            return $select;
        }

        if (array_key_exists($name, static::$belongs_to)) {
            $desc = static::$belongs_to[$name];
            $key = isset($desc['key']) ? $desc['key'] : $desc['class']::table().'_id';
            if (array_key_exists($key, $this->_data)) {
                $tmp = $desc['class']::select()->where('id = ?', $this->_data[$key]);
                return $tmp;
            }
        }

        return self::$_false;
    }

    function validate()
    {
        return true;
    }

    function save()
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->_data['id']) {
            if (count($this->_dirty) == 0) {
                return true;
            }

            $data = array();
            foreach (array_keys($this->_dirty) as $k) {
                $data[$k] = $this->_data[$k];
            }

            self::$db->update(static::table(), $data, self::$db->quoteInto('id = ?', $this->_data['id']));
        } else {
            self::$db->insert(static::table(), $this->_data);
            $this->_data['id'] = self::$db->lastInsertId();
        }

        $this->_dirty = array();
        return true;
    }
}
