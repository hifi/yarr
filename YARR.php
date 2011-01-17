<?php

/**
 * Yet another ActiveRecoRd
 *
 * Implements a minimal ActiveRecord pattern in a single class.
 *
 * @author Toni Spets <toni.spets@iki.fi>
 */

abstract class YARR
{
    /* shared statics */
    static $db;
    static $treat_empty_as_null;

    /* exceptions */
    const E_ONLY_PARENT = 0xF001;
    const E_NO_SCHEMA   = 0xF002;
    const E_NO_TYPE     = 0xF003;
    const E_UNK_TYPE    = 0xF003;
    const E_INVALID     = 0xF004;

    /* child extension */
    static $table;
    static $schema;

    /* child object private */
    private $attributes;
    private $errors;
    private $__dirty;

    function __construct($data = array())
    {
        $this->__dirty = array();
        $this->attributes = array('id' => NULL);

        foreach(static::$schema as $k => $v) {
            if (array_key_exists($k, $data)) {
                if (is_bool($data[$k])) $data[$k] = (int)$data[$k]; // FIXME: generalize
                $this->attributes[$k] = $data[$k];
            } else {
                if (array_key_exists('default', $v)) {
                    if (is_bool($v['default'])) $v['default'] = (int)$v['default']; // FIXME: generalize
                    $this->attributes[$k] = $v['default'];
                } else {
                    /* even if not null is set, we default to null, saving or validating will just fail */
                    $this->attributes[$k] = NULL;
                }
            }
        }
    }

    function &__get($k)
    {
        if ($k == 'errors') {
            return $this->errors;
        }

        if (array_key_exists($k, $this->attributes)) {
            /* when getting a has_many relation, replace the internal integer value with an object, save will use it's id */
            if (array_key_exists($k, static::$schema) && static::$schema[$k]['type'] == 'has_many') {
                if ($this->attributes[$k] !== NULL && is_numeric($this->attributes[$k])) {
                    $this->attributes[$k] = call_user_func(static::$schema[$k]['class'].'::find', 'first', array('where' => array('id = ?', $this->attributes[$k])));
                }
            }
            return $this->attributes[$k];
        }
    }

    function __set($k, $v)
    {
        /* convert booleans to numeric values */
        if (is_bool($v)) $v = (int)$v;

        if (array_key_exists($k, $this->attributes)) {
            /* disallow saving a wrong object in has_many relation */
            if (static::$schema[$k]['type'] == 'has_many') {
                if (is_object($v) && get_class($v) != static::$schema[$k]['class']) {
                    return false;
                }
            }
            $this->__dirty[$k] = true;
            return ($this->attributes[$k] = $v);
        }

        return false;
    }

    function __isset($k)
    {
        return isset($this->attributes[$k]);
    }

    function __unset($k)
    {
        return ($this->attributes[$k] = NULL);
    }

    function __clone()
    {
        $this->attributes['id'] = NULL;
    }

    function toArray()
    {
        /* FIXME: trigger has_many relations so the result will have objects instead of integers */
        return (array)(clone (object)$this->attributes);
    }

    /**
     * $keys = array of primary keys, 'all', 'first' or 'last'
     * $options = (
     *      'where'     => 'WHERE' or array('WHERE', bind, bind, ...),
     *      'limit'     => int,
     *      'offset'    => int,
     *      'order'     => 'ORDER BY',
     *      'select'    => 'SELECT ? FROM',
     *      'from'      => 'a AS b',
     *      'group      => 'GROUP BY',
     *      'having'    => 'HAVING'
     * )
     */
    function find($keys, $options)
    {
        return NULL;
    }

    function validate()
    {
        $this->errors = array();

        foreach(static::$schema as $k => $data) {
            switch($data['type']) {
                case 'string':
                    if (array_key_exists('size', $data) && strlen($this->attributes[$k]) > $data['size']) {
                        $this->errors[] = $k;
                    }
                    break;
                case 'has_many':
                    /* can't save a relation if the relation itself ain't saved first */
                    if (is_object($this->attributes[$k])) {
                        if ($this->attributes[$k]->id === NULL) {
                            $this->errors[] = $k;
                        }
                    }
                    else if (!preg_match('/^\d*$/', $this->attributes[$k])) {
                        $this->errors[] = $k;
                    }
                    break;
                case 'boolean':
                case 'integer':
                    if (!preg_match('/^\d*$/', $this->attributes[$k])) {
                        $this->errors[] = $k;
                    }
                    break;
                case 'decimal':
                    if (!preg_match('/^((\d*)|(\d+\.\d+))$/', $this->attributes[$k])) {
                        $this->errors[] = $k;
                    }
                    break;
                case 'datetime';
                    if (!preg_match('/^(\d\d\d\d\-\d\d\-\d\d \d\d:\d\d:\d\d){0,1}$/', $this->attributes[$k])) {
                        $this->errors[] = $k;
                    }
                    break;
                default:
                    throw new Exception("Unknown type '{$data['type']}'", self::E_UNK_TYPE);
            }

            if (array_key_exists('null', $data) && !$data['null'] && is_null($this->attributes[$k])) {
                $this->errors[] = $k;
            }

            if (array_key_exists('unique', $data) && $data['unique']) {
                if (!is_null(self::find('first', array('where' => array('id = ?', $this->attributes[$k])))))
                    $this->errors[] = $k;
            }
        }

        if (count($this->errors) > 0) {
            return false;
        }

        $this->errors = NULL;
        return true;
    }

    function save()
    {
        if (!$this->validate())
            throw new Exception('Validation failed in save', self::E_INVALID);

        if(is_null($this->attributes['id'])) {
            $this->insert();
        } else {
            $this->update();
        }

        $this->__dirty = array();
    }

    private function insert()
    {
        $class = get_class($this);

        if (is_null(static::$table)) {
            $table = strtolower($class);
        } else {
            $table = static::$table;
        }

        $data = array();

        foreach($this->attributes as $k => $v) {
            if ($k == 'id')
                continue;
            $data[self::quoteName($k)] = self::quote($k, $v);
        }

        self::$db->exec('INSERT INTO '.self::quoteName($table, true).' ('.implode(array_keys($data), ',').') VALUES('.implode($data, ',').')');

        $this->attributes['id'] = self::$db->lastInsertId();
    }

    private function update()
    {
        if(count($this->__dirty) == 0)
            return;

        $class = get_class($this);

        if (is_null(static::$table)) {
            $table = strtolower($class);
        } else {
            $table = static::$table;
        }

        $pairs = array();
        foreach(array_keys($this->__dirty) as $k) {
            $pairs[] = self::quoteName($k).' = '.self::quote($k, $this->attributes[$k]);
        }

        self::$db->exec('UPDATE '.self::quoteName($table, true).' SET '.implode($pairs, ',').' WHERE '.self::quoteName('id').' = '.self::quote($k, $this->attributes['id']));
    }

    static function init(PDO $db, $treat_empty_as_null = true)
    {
        static::$db = $db;
        static::$treat_empty_as_null = $treat_empty_as_null;

        /* have to do this, we require exceptions for our flow to work */
        static::$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }

    private static function quote($key, $value)
    {
        /* save has_many relations correctly */
        if (static::$schema[$key]['type'] == 'has_many') {
            if (is_object($value))
                $value = $value->id;
        }

        /* integer values as integer */
        if (preg_match('/^\d+\.{0,1}\d*$/', $value)) {
            return (string)$value;
        }

        if (is_null($value) || (self::$treat_empty_as_null && strlen($value) == 0))
            return 'NULL';

        return self::$db->quote($value);
    }

    private static function quoteName($str, $raw = false)
    {
        /* has_many relation key handling */
        if (!$raw) {
            if (static::$schema[$str]['type'] == 'has_many') {
            if ($str == 'host')
                if (array_key_exists('key', static::$schema[$str])) {
                    $str = static::$schema[$str];
                } else {
                    $str = $str.'_id';
                }
            }
        }
        /* FIXME: backtick is not ANSI SQL (MySQL uses it, SQLite has compatibility) */
        return "`{$str}`";
    }

    static function schemaToSQL()
    {
        $class = get_called_class();

        if (!is_array(static::$schema))
            throw new Exception('No schema in class', self::E_NO_SCHEMA);

        if (is_null(static::$table)) {
            $table = strtolower($class);
        } else {
            $table = static::$table;
        }

        ob_start();

        echo "CREATE TABLE ".self::quoteName($table, true)." (\n";
        echo "\t".self::quoteName('id', true)." INTEGER PRIMARY KEY";

        foreach(static::$schema as $name => $data) {
            if (!array_key_exists('type', $data))
                throw new Exception('No type specified for column', self::E_NO_TYPE);

            echo ",\n\t";
            echo self::quoteName($name);

            switch($data['type']) {
                case 'string':
                    if(array_key_exists('size', $data)) {
                        echo " VARCHAR({$data['size']})";
                    } else {
                        echo ' TEXT';
                    }
                    break;
                case 'integer':
                    echo ' INTEGER';
                    if (array_key_exists('signed', $data)) {
                        if ($data['signed']) {
                            echo ' SIGNED';
                        } else {
                            echo ' UNSIGNED';
                        }
                    }
                    break;
                case 'boolean':
                    echo ' UNSIGNED INTEGER(1)';
                    break;
                case 'decimal':
                    echo ' DECIMAL';
                    if (array_key_exists('size', $data)) {
                        echo "({$data['size']})";
                    }
                    break;
                case 'has_many':
                    echo ' INTEGER';
                    break;
                case 'datetime':
                    echo ' DATETIME';
                    break;
                default:
                    throw new Exception("Unknown type '{$data['type']}'", self::E_UNK_TYPE);
            }

            if (array_key_exists('null', $data)) {
                if ($data['null']) {
                    echo ' NULL';
                } else {
                    echo ' NOT NULL';
                }
            }

            if (array_key_exists('unique', $data) && $data['unique']) {
                echo ' UNIQUE';
            }

            if (array_key_exists('default', $data)) {
                echo ' DEFAULT';
                if (is_null($data['default'])) {
                    echo ' NULL';
                } else {
                    if ($data['type'] == 'boolean') {
                        echo ' '.(int)$data['default'];
                    } else {
                        echo ' '.static::$db->quote($data['default']);
                    }
                }
            }
        }

        echo "\n);\n";

        return ob_get_clean();
    }
}
