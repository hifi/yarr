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

        if(array_key_exists($k, $this->attributes))
            return $this->attributes[$k];

        return NULL;
    }

    function __set($k, $v)
    {
        /* convert booleans to numeric values */
        if (is_bool($v)) $v = (int)$v;

        if (array_key_exists($k, $this->attributes)) {
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
                default:
                    throw new Exception("Unknown type '{$data['type']}'", self::E_UNK_TYPE);
            }

            if (array_key_exists('null', $data) && !$data['null'] && is_null($this->attributes[$k])) {
                $this->errors[] = $k;
            }

            if (array_key_exists('unique', $data) && $data['unique']) {
                /* TODO: find by this column to validate? */
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
            if($k == 'id')
                continue;
            $data[self::quoteName($k)] = self::quote($v);
        }

        self::$db->exec('INSERT INTO '.self::quoteName($table).' ('.implode(array_keys($data), ',').') VALUES('.implode($data, ',').')');

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
            $pairs[] = self::quoteName($k).' = '.self::quote($this->attributes[$k]);
        }

        self::$db->exec('UPDATE '.self::quoteName($table).' SET '.implode($pairs, ',').' WHERE '.self::quoteName('id').' = '.self::quote($this->attributes['id']));
    }

    static function init(PDO $db, $treat_empty_as_null = true)
    {
        static::$db = $db;
        static::$treat_empty_as_null = $treat_empty_as_null;

        /* have to do this, we require exceptions for our flow to work */
        static::$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }

    private static function quote($str)
    {
        if (is_null($str) || (self::$treat_empty_as_null && strlen($str) == 0))
            return 'NULL';

        return self::$db->quote($str);
    }

    private static function quoteName($str)
    {
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

        echo "CREATE TABLE ".self::quoteName($table)." (\n";
        echo "\t".self::quoteName('id')." INTEGER PRIMARY KEY";

        foreach(static::$schema as $name => $data) {
            if (!array_key_exists('type', $data))
                throw new Exception('No type specified for column', self::E_NO_TYPE);

            echo ",\n\t".self::quoteName($name);

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
                case 'decimal':
                    echo ' DECIMAL';
                    if(array_key_exists('size', $data)) {
                        echo "({$data['size']})";
                    }
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
                    echo ' '.static::$db->quote($data['default']);
                }
            }
        }

        echo "\n);\n";

        return ob_get_clean();
    }
}
