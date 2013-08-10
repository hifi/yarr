<?php

/*
 * Copyright (c) 2011, 2013 Toni Spets <toni.spets@iki.fi>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

require_once 'Zend/Db/Adapter/Abstract.php';

require_once 'Zend/Db.php';

require_once 'YARR/Select.php';

require_once 'YARR/Format.php';

abstract class YARR_Abstract
{
    const table = false;

    private static $adapters = array();
    private static $fields = array();

    protected static $belongs_to = array();
    protected static $has_one = array();
    protected static $has_many = array();
    protected static $has_and_belongs_to_many = array();

    protected $_data;
    protected $_dirty;
    protected $_format;

    protected $_belongs_to;
    protected $_has_one;
    protected $_has_many;
    protected $_has_and_belongs_to_many;

    public $errors = array();

    static public function setAdapter(Zend_Db_Adapter_Abstract $adapter)
    {
        $class = get_called_class();
        self::$adapters[$class] = $adapter;
    }

    static public function getAdapter()
    {
        $class = get_called_class();

        if (!array_key_exists($class, self::$adapters)) {
            if (array_key_exists('YARR_Abstract', self::$adapters)) {
                return self::$adapters['YARR_Abstract'];
            }

            throw new Exception('No database adapters configured for YARR.');
        }

        return self::$adapters[$class];
    }

    static public function table()
    {
        $class = get_called_class();
        $table = static::table;

        if (!$table) {
            $tmp = explode('_', strtolower($class));
            foreach ($tmp as $k => $v) {
                if ($v[strlen($v)-1] == 's') {
                    $tmp[$k] .= 'es';
                } else {
                    $tmp[$k] .= 's';
                }
            }
            $table = implode('_', $tmp);
        }

        return $table;
    }

    static public function fields()
    {
        $class = get_called_class();

        if (!array_key_exists($class, self::$fields)) {
            self::$fields[$class] = static::getAdapter()->describeTable(static::table());
        }

        return self::$fields[$class];
    }

    function __construct($data)
    {
        $this->_data = array();
        $this->_dirty = array();
        $this->_belongs_to = array();
        $this->_has_one = array();
        $this->_has_many = array();
        $this->_has_and_belongs_to_many = array();

        foreach (static::fields() as $k => $desc) {
            $this->_data[$k] = null;
        }

        $this->_data = array_replace($this->_data, $data);

        if (YARR_Format::initialized())
            $this->_format = new YARR_Format($this);
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
        return YARR_Select::fromObject(get_called_class(), static::getAdapter());
    }

    function __get($k)
    {
        if ($this->_format && $this->_format->supported($k)) {
            return $this->_format->type($k);
        }

        if (array_key_exists($k, static::$belongs_to)) {
            if (!array_key_exists($k, $this->_belongs_to)) {
                $this->_belongs_to[$k] = $this->$k()->getOne();
            }
            return $this->_belongs_to[$k];
        }

        if (array_key_exists($k, static::$has_one)) {
            if (!array_key_exists($k, $this->_has_one)) {
                $this->_has_one[$k] = $this->$k()->getOne();
            }
            return $this->_has_one[$k];
        }

        if (array_key_exists($k, static::$has_many)) {
            if (!array_key_exists($k, $this->_has_many)) {
                $this->_has_many[$k] = $this->$k()->getAll();
            }
            return $this->_has_many[$k];
        }

        if (array_key_exists($k, static::$has_and_belongs_to_many)) {
            if (!array_key_exists($k, $this->_has_and_belongs_to_many)) {
                $this->_has_and_belongs_to_many[$k] = $this->$k()->getAll();
            }
            return $this->_has_and_belongs_to_many[$k];
        }

        if (array_key_exists($k, $this->_data)) {
            return $this->_data[$k];
        }

        return null;
    }

    function __set($k, $v)
    {
        if ($this->_format && $this->_format->supported($k)) {
            return $this->_format->type($k);
        }

        if (array_key_exists($k, $this->_data)) {
            $this->_dirty[$k] = true;
        }

        return ($this->_data[$k] = $v);
    }

    function __call($name, $args)
    {
        $desc = false;

        if (array_key_exists($name, static::$belongs_to)) {
            $desc = static::$belongs_to[$name];
            $local = isset($desc['local']) ? $desc['local'] : strtolower($desc['class']).'_id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : 'id';
        }

        if (array_key_exists($name, static::$has_one)) {
            $desc = static::$has_one[$name];
            $local = isset($desc['local']) ? $desc['local'] : 'id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : strtolower($desc['class']).'_id';
        }

        if (array_key_exists($name, static::$has_many)) {
            $desc = static::$has_many[$name];
            $local = isset($desc['local']) ? $desc['local'] : 'id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : strtolower($desc['class']).'_id';
        }

        if ($desc) {
            $select = $desc['class']::select();
            $adapter = $select->getAdapter();
            if (array_key_exists($local, $this->_data)) {
                return $select->where($adapter->quoteIdentifier($desc['class']::table() . '.' . $foreign).' = ?', $this->_data[$local]);
            }
        }

        if (array_key_exists($name, static::$has_and_belongs_to_many)) {
            $desc = static::$has_and_belongs_to_many[$name];
            $local = isset($desc['local']) ? $desc['local'] : strtolower(get_class($this)).'_id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : strtolower($desc['class']).'_id';

            $my_table = static::table();
            $their_table = $desc['class']::table();
            if (isset($desc['join_table']))
                $join_table = $desc['join_table'];
            else if ($my_table < $their_table)
                $join_table = $my_table . '_' . $their_table;
            else
                $join_table = $their_table . '_' . $my_table;

            $select = $desc['class']::select();
            $adapter = $select->getAdapter();
            $select->join(
                $join_table,
                $adapter->quoteIdentifier($join_table . '.' . $local) . ' = ' . $adapter->quote($this->_data['id']).
                ' AND '.
                $adapter->quoteIdentifier($join_table . '.' . $foreign) . ' = '. $adapter->quoteIdentifier($their_table . '.id')
                , ''
            );

            return $select;
        }

        return false;
    }

    public function toArray()
    {
        return $this->_data;
    }

    public function validate()
    {
        $this->errors = array();
        $fields = $this->fields();

        foreach ($this->fields() as $k => $field) {
            $data = $this->_data[$k];

            switch (strtoupper($field['DATA_TYPE'])) {
                case 'VARCHAR':
                    if (strlen($data) > $field['LENGTH'])
                        $this->errors[$k][] = 'maximum length of ' . $field['LENGTH'] . ' exceeded';
                    break;
                case 'INT':
                case 'INTEGER':
                    if (!preg_match('/^-?[0-9]*$/', $data))
                        $this->errors[$k][] = 'not integer';
                    else if ($field['UNSIGNED'] && $data < 0)
                        $this->errors[$k][] = 'only unsigned integers allowed';
                    break;
                case 'DECIMAL':
                    if (!preg_match('/^-?[0-9]*\.?[0-9]*$/', $data))
                        $this->errors[$k][] = 'not decimal';
                    break;
                case 'TEXT':
                    break;
                default:
                    break;
            }

            if (!$field['NULLABLE'] && $data === null && $k != 'id' && ($this->_data['id'] == null && !$field['DEFAULT'] || $this->_data['id'])) {
                $this->errors[$k][] = 'cannot be null';
            }
        }

        return (count($this->errors) == 0);
    }

    public function save()
    {
        if (!$this->validate()) {
            throw new Exception('Validate failed.');
        }

        $adapter = static::getAdapter();
        $table = static::table();
        $table_id = $adapter->quoteIdentifier($table . '.id');
        $fields = $this->fields();

        $data = array();
        foreach (array_keys($this->_dirty) as $k) {
            if (array_key_exists($k, $fields))
                $data[$k] = $this->_data[$k];
        }

        if ($this->_data['id']) {
            if (count($data) == 0) {
                return;
            }

            $adapter->update($table, $data, $adapter->quoteInto($table_id . ' = ?', $this->_data['id']));
        } else {
            $adapter->insert(static::table(), $data);
            $this->_data['id'] = $adapter->lastInsertId();
        }

        $this->_dirty = array();
        $this->_data = static::select()->where($table_id . ' = ?', $this->_data['id'])->asArray()->getOne();
    }

    public function delete()
    {
        if ($this->_data['id']) {
            $adapter = static::getAdapter();
            $table = static::table();
            $adapter->delete($table, $adapter->quoteInto($adapter->quoteIdentifier($table . '.id') . ' = ?', $this->_data['id']));
            $this->_data['id'] = null;
            $this->_dirty = array_keys($this->_data);
        }
    }
}
