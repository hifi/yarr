<?php

/*
 * Copyright (c) 2011 Toni Spets <toni.spets@iki.fi>
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

abstract class YARR_Abstract
{
    const table = false;

    private static $db = false;
    private static $fields = array();

    protected static $has_one = array();
    protected static $has_many = array();
    protected static $has_and_belongs_to_many = array();

    protected $_data;
    protected $_dirty;
    protected $_has_one;
    protected $_has_many;
    protected $_has_and_belongs_to_many;

    static public function setDefaultAdapter(Zend_Db_Adapter_Abstract $db)
    {
        self::$db = $db;
    }

    static public function getDefaultAdapter()
    {
        return self::$db;
    }

    static public function table()
    {
        $class = get_called_class();
        $table = constant($class.'::table');

        if (!$table) {
            $table = strtolower($class);
            if ($table[strlen($table)-1] == 's') {
                $table .= 'es';
            } else {
                $table .= 's';
            }
        }

        return $table;
    }

    static public function fields()
    {
        $class = get_called_class();

        if (!array_key_exists($class, self::$fields)) {
            self::$fields[$class] = self::$db->describeTable(static::table());
        }

        return self::$fields[$class];
    }

    function __construct($data)
    {
        $this->_data = array();
        $this->_dirty = array();
        $this->_has_one = array();
        $this->_has_many = array();
        $this->_has_and_belongs_to_many = array();

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

    function __get($k)
    {
        if (array_key_exists($k, static::$has_many)) {
            if (!array_key_exists('data', $this->_has_many)) {
                $this->_has_many[$k] = $this->$k()->getAll();
            }
            return $this->_has_many[$k];
        }

        if (array_key_exists($k, static::$has_one)) {
            if (!array_key_exists('data', $this->_has_one)) {
                $this->_has_one[$k] = $this->$k()->getOne();
            }
            return $this->_has_one[$k];
        }

        if (array_key_exists($k, static::$has_and_belongs_to_many)) {
            if (!array_key_exists('data', $this->_has_and_belongs_to_many)) {
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
        if (array_key_exists($k, $this->_data)) {
            $this->_dirty[$k] = true;
            return ($this->_data[$k] = $v);
        }

        return false;
    }

    function __call($name, $args)
    {
        $desc = false;

        if (array_key_exists($name, static::$has_many)) {
            $desc = static::$has_many[$name];
            $local = isset($desc['local']) ? $desc['local'] : 'id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : strtolower($desc['class']).'_id';
        }

        if (array_key_exists($name, static::$has_one)) {
            $desc = static::$has_one[$name];
            $local = isset($desc['local']) ? $desc['local'] : strtolower($desc['class']).'_id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : 'id';
        }

        if ($desc) {
            $select = $desc['class']::select();
            $db = $select->getAdapter();
            if (array_key_exists($local, $this->_data)) {
                return $select->where($db->quoteIdentifier($foreign).' = ?', $this->_data[$local]);
            }
        }

        if (array_key_exists($name, static::$has_and_belongs_to_many)) {
            $desc = static::$has_and_belongs_to_many[$name];
            $local = isset($desc['local']) ? $desc['local'] : strtolower(get_class($this)).'_id';
            $foreign = isset($desc['foreign']) ? $desc['foreign'] : strtolower($desc['class']).'_id';

            $my_table = static::table();
            $their_table = $desc['class']::table();
            if ($my_table < $their_table)
                $join_table = $my_table . '_' . $their_table;
            else
                $join_table = $their_table . '_' . $my_table;

            $select = $desc['class']::select();
            $db = $select->getAdapter();
            $select->join(
                $join_table,
                $db->quoteIdentifier($join_table) . '.' . $db->quoteIdentifier($local) . ' = ' . $db->quote($this->_data['id']).
                ' AND '.
                $db->quoteIdentifier($join_table) . '.' . $db->quoteIdentifier($foreign) . ' = '.$db->quoteIdentifier($their_table) . '.' . $db->quoteIdentifier('id')
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
        return true;
    }

    public function save()
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

    public function delete()
    {
        if ($this->_data['id']) {
            self::$db->delete(static::table(), self::$db->quoteInto('id = ?', $this->_data['id']));
            $this->_data['id'] = null;
            $this->_dirty = array_keys($this->_data);
            return true;
        }

        return false;
    }
}
